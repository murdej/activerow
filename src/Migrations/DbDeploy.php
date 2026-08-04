<?php

namespace Murdej\ActiveRow\Migrations;

use Murdej\ActiveRow\ColumnInfo;
use Murdej\ActiveRow\Interfaces\DbTypeDriver;
use Murdej\ActiveRow\TableInfo;

class DbDeploy
{
    const OrderCol = 10;

    const OrderTable = 10;

    const OrderIndex = 20;

    const OrderFK = 30;

    public function __construct(
        protected DbTypeDriver $driver = new MariaDB(),
    ) {
    }

    public function createTable(TableInfo $ti, array &$sqlParts)
    {
        $nl = "\n";
        $tableSqlParts = [
            self::OrderCol => [],
            self::OrderIndex => [],
            self::OrderFK => [],
        ];
        $columnsByPropertyName = []; // $ti->columns;
        foreach ($ti->columns as $column) $columnsByPropertyName[$column->propertyName] = $column;
        foreach ($columnsByPropertyName as $k => $column) {
            if ($column->fkClass && isset($columnsByPropertyName[$column->propertyName]))
                $columnsByPropertyName[$column->columnName] = $column;
        }
        foreach (array_unique(array_map(fn($c) => $c->columnName, $columnsByPropertyName)) as $k) {
            $column = $columnsByPropertyName[$k];
            $this->createColumn($column, $tableSqlParts, $ti->columns);
        }
        if (!$ti->tableName) throw new \Exception("No tableName for " . $ti->className);
        $sql = 'CREATE TABLE ' . $this->driver->escapeIdentifier($ti->tableName) . " ($nl";
        $sql .= implode(",$nl", $tableSqlParts[self::OrderCol]);
        $sql .= "$nl)";

        $sqlParts[self::OrderTable][] = $sql;

        foreach ([self::OrderIndex, self::OrderFK] as $i) {
            foreach ($tableSqlParts[$i] as $item) {
                $sqlParts[$i][] = $item;
            }
        }
    }

    /**
     * @param TableInfo[] $tis
     */
    public function createTables(array $tis): string
    {
        $sqlParts = [];
        foreach ($tis as $ti) {
            $this->createTable($ti, $sqlParts);
        }
        $sql = '';
        ksort($sqlParts);
        foreach ($sqlParts as $sqlPart) {
            $sql .= implode(";\n", $sqlPart) . ";\n";
        }

        return $sql;
    }

    /**
     * @param TableInfo[][] $tis
     */
    public function syncTables(array $tis): string
    {
        $sqlParts = [];
        foreach ($tis as [$tia, $tid]) {
            if ($tid) {
                $this->alterTable($tia, $tid, $sqlParts);
            } else {
                $this->createTable($tia, $sqlParts);
            }
        }
        $sql = '';
        ksort($sqlParts);
        foreach ($sqlParts as $sqlPart) {
            $sqlItem = implode(";\n", $sqlPart);
            if ($sqlItem) $sql .= $sqlItem . ";\n";
        }

        return $sql;
    }

    /**
     * @param ColumnInfo $column
     * @param array $sqlParts
     * @param ColumnInfo[] $allColumns
     * @return void
     * @throws \Exception
     */
    public function createColumn(
        ColumnInfo $column,
        array &$sqlParts,
        array $allColumns,
    ) {
        $line = [];
        $identical = true;

        if ($identical) {
            $line[] = $this->driver->escapeIdentifier($column->columnName);
            $t = $this->resolveSqlDataType($column);
            $line[] = $t[0];
            $line[] = $column->nullable ? 'NULL' : 'NOT NULL';
            if ($column->autoIncrement) {
                // autoIncrementClause() already includes the primary-key constraint.
                $line[] = $this->driver->autoIncrementClause();
            }
            if ($column->unique) $line[] = 'UNIQUE KEY';
            if ($column->primary && !$column->autoIncrement) $line[] = 'PRIMARY KEY';
            if (isset($t[1])) $line[] = $t[1];

            $joinLine = implode(' ', $line);
            $sqlParts[self::OrderCol][] = $joinLine;
        }

        // fk
        if ($column->fkClass) {
            $fkTi = $column->getFkTableInfo();
            $sqlParts[self::OrderFK][] = 'ALTER TABLE ' . $this->driver->escapeIdentifier($column->tableInfo->tableName) . ' ADD FOREIGN KEY (' . $this->driver->escapeIdentifier($column->columnName) . ') REFERENCES '
                . $this->driver->escapeIdentifier($fkTi->tableName)
                . ' (' . $this->driver->escapeIdentifier(reset($fkTi->primary)->columnName) . ')';
        }
        if ($column->indexed) {
            $sqlParts[self::OrderIndex][] = 'ALTER TABLE ' . $this->driver->escapeIdentifier($column->tableInfo->tableName) . ' ADD INDEX (' . $this->driver->escapeIdentifier($column->columnName) . ')';
        }
    }

    /**
     * Resolves the SQL type used to define/compare a column. If the column declares an explicit
     * `dbType=` override, it is used verbatim, bypassing the driver's PHP-type-to-SQL-type
     * conversion table entirely.
     *
     * @return array{0: string, 1: ?string} [sqlType, checkClauseOrNull]
     */
    protected function resolveSqlDataType(ColumnInfo $column): array
    {
        return $column->dbType !== null
            ? [$column->dbType, null]
            : $this->driver->getSqlDataType($column);
    }

    public function alterTable(TableInfo $appTable, TableInfo $dbTable, array &$sqlAllParts): void
    {
        $sqlAllParts += [
            self::OrderCol => [],
            self::OrderFK => [],
            self::OrderIndex => [],
        ];

        $dbColumnsByName = [];
        foreach ($dbTable->columns as $column) $dbColumnsByName[$column->columnName] = $column;
        $columns = $appTable->columns;
        // Clear *Id when exists foreign
        foreach ($columns as $k => $column) {
            if ($column->columnName !== $column->propertyName) {
                unset($columns[$column->columnName]);
            }
        }
        foreach ($columns as $appColumn) {
            $dbColumn = $dbColumnsByName[$appColumn->columnName] ?? null;
            $sqlParts = [
                self::OrderCol => [],
                self::OrderFK => [],
                self::OrderIndex => [],
            ];
            if ($dbColumn) {
                $this->createColumn($appColumn, $sqlParts, $appTable->columns);
                if ($dbColumn->nullable != $appColumn->nullable
                    || $this->driver->hasTypeChanged($dbColumn, $this->resolveSqlDataType($appColumn)[0])
                ) {
                    foreach ($sqlParts[self::OrderCol] as $colSql) {
                        $sqlAllParts[self::OrderCol][] = 'ALTER TABLE ' . $this->driver->escapeIdentifier($appTable->tableName) . " MODIFY  $colSql";
                    }
                }
                $appFkTableInfo = $appColumn->getFkTableInfo();
                if ($dbColumn->fkTable != $appFkTableInfo?->tableName) {
                    $sqlAllParts[self::OrderFK] = array_merge(
                        $sqlAllParts[self::OrderFK],
                        $sqlParts[self::OrderFK]
                    );
                    if (!$appFkTableInfo) {
                        $sqlAllParts[self::OrderFK][] = "-- TODO: Remove foreign key for " . $appColumn->columnName . " to " . $dbColumn->fkTable;
                    }
                }
                if (!$dbColumn->indexed && $appColumn->indexed) {
                    $sqlAllParts[self::OrderFK] = array_merge(
                        $sqlAllParts[self::OrderFK],
                        $sqlParts[self::OrderFK]
                    );
                }
                if ($dbColumn->indexed && !$appColumn->indexed && !$appColumn->fkClass) {
                    $sqlAllParts[self::OrderIndex][] = "-- TODO: Remove index for " . $appColumn->columnName;
                }
            } else {
                $this->createColumn($appColumn, $sqlParts, $appTable->columns);
                foreach ($sqlParts[self::OrderCol] as $colSql) {
                    $sqlAllParts[self::OrderCol][] = 'ALTER TABLE ' . $this->driver->escapeIdentifier($appTable->tableName) . " ADD $colSql";
                }
                $sqlAllParts[self::OrderFK] = array_merge($sqlAllParts[self::OrderFK], $sqlParts[self::OrderFK]);
                $sqlAllParts[self::OrderIndex] = array_merge($sqlAllParts[self::OrderIndex], $sqlParts[self::OrderIndex]);
            }
        }
    }
}
