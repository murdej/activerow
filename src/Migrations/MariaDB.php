<?php

namespace Murdej\ActiveRow\Migrations;

use Murdej\ActiveRow\AbstractDatabase;
use Murdej\ActiveRow\ColumnInfo;
use Murdej\ActiveRow\Interfaces\DbTypeDriver;
use Murdej\ActiveRow\TableInfo;

class MariaDB implements DbTypeDriver
{
    public function escapeIdentifier(string $name): string
    {
        return "`$name`";
    }

    public function getSqlDataType(ColumnInfo $column): array
    {
        $ch = null;
        $escCN = $this->escapeIdentifier($column->columnName);
        switch ($column->dbBaseType ?? $column->type) {
            case 'int':
                $t = 'INT(' . ($column->typeLen ? $column->typeLen : 10) . ')';
                break;
            case 'decimal':
            case 'double':
            case 'float':
                $l = $column->typeLen ?: 10;
                $d = $column->typeDec ?: 3;
                $t = strtoupper($column->type) . '(' . ($l + $d) . ',' . $d . ')';
                break;
            case 'json':
            case 'string':
                $t = $column->typeLen
                    ? 'VARCHAR(' . $column->typeLen . ')'
                    : 'TEXT';
                if ($column->type == 'json') $ch = "CHECK($escCN IS NULL OR JSON_VALID($escCN))";
                break;
            case 'bool':
                $t = 'TINYINT(1)';
                $ch = "CHECK($escCN IN (1, 0))";
                break;
            case 'DateTime':
            case '\\DateTime':
                $t = 'DATETIME';
                break;
            default:
                $enumRef = null;
                if (enum_exists($column->type)) {
                    try {
                        $enumRef = new \ReflectionEnum($column->type);
                    } catch (\ReflectionException) {
                        // enum_exists() said yes but the class still couldn't be resolved (e.g. a
                        // stale/optimized classmap listing a class its autoloader can no longer
                        // find) — fall through to the "unknown type" exception below instead of
                        // letting a raw, entity-less ReflectionException escape.
                        $enumRef = null;
                    }
                }
                if ($enumRef?->isBacked()) {
                    if ($enumRef->getBackingType()->getName() === 'int') {
                        $t = 'INT(' . ($column->typeLen ?: 10) . ')';
                    } else {
                        $maxLen = 0;
                        foreach ($column->type::cases() as $case) {
                            $maxLen = max($maxLen, strlen((string) $case->value));
                        }
                        $t = 'VARCHAR(' . ($column->typeLen ?: $maxLen) . ')';
                    }
                    break;
                }
                $typeDesc = $column->dbBaseType !== null ? "$column->type (dbBaseType=$column->dbBaseType)" : $column->type;
                $hint = class_exists($column->type) || enum_exists($column->type)
                    ? ''
                    : ' — class/enum could not be autoloaded, check its namespace/PSR-4 mapping';
                throw new \Exception("I don't know how to convert the '$typeDesc' type to a DB type for " . ($column->tableInfo ? $column->tableInfo->className . '::' : '') . "$column->propertyName" . $hint);
        }
        return [$t, $ch];
    }

    public function autoIncrementClause(): string
    {
        return 'AUTO_INCREMENT PRIMARY KEY';
    }

    public function hasTypeChanged(ColumnInfo $liveColumn, string $generatedSqlType): bool
    {
        return $liveColumn->liveType !== strtolower($generatedSqlType);
    }

    public function loadTableInfo(AbstractDatabase $db, string $tableName): ?TableInfo
    {
        if (!$this->existsTable($db, $tableName)) return null;

        $columns = [];
        $tableInfo = new TableInfo(null);
        foreach ($db->dbExecuteQuery('DESCRIBE ' . $this->escapeIdentifier($tableName), []) as $dbColumn) {
            $column = new ColumnInfo(null, null, $tableInfo);
            $column->columnName = $dbColumn['Field'];
            $column->defaultValue = $dbColumn['Default'];
            $column->liveType = $dbColumn['Type'];
            $column->autoIncrement = $dbColumn['Extra'] === 'auto_increment';
            $column->nullable = $dbColumn['Null'] == 'YES';
            $columns[$dbColumn['Field']] = $column;
            $tableInfo->columns[$dbColumn['Field']] = $column;
        }

        foreach ($db->dbExecuteQuery(
            'SELECT * FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_NAME IS NOT NULL
                    AND TABLE_NAME = ?
                    AND TABLE_SCHEMA = DATABASE()',
            [$tableName]
        ) as $foreignKey) {
            $columns[$foreignKey['COLUMN_NAME']]->fkTable = $foreignKey['REFERENCED_TABLE_NAME'];
        }

        foreach ($db->dbExecuteQuery('SHOW INDEX FROM ' . $this->escapeIdentifier($tableName), []) as $row) {
            $columns[$row['Column_name']]->indexed = (bool) $row['Non_unique'];
        }

        return $tableInfo;
    }

    public function existsTable(AbstractDatabase $db, string $tableName): bool
    {
        return (bool) $db->dbExecuteQuery(
            'SELECT * FROM information_schema.TABLES
                WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()',
            [$tableName]
        );
    }
}
