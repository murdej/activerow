<?php

namespace Murdej\ActiveRow\Interfaces;

use Murdej\ActiveRow\AbstractDatabase;
use Murdej\ActiveRow\ColumnInfo;
use Murdej\ActiveRow\TableInfo;

interface DbTypeDriver
{
    public function escapeIdentifier(string $name): string;

    /** @return array{0: string, 1: ?string} [sqlType, checkClauseOrNull] */
    public function getSqlDataType(ColumnInfo $column): array;

    public function autoIncrementClause(): string;

    public function hasTypeChanged(ColumnInfo $liveColumn, string $generatedSqlType): bool;

    public function loadTableInfo(AbstractDatabase $db, string $tableName): ?TableInfo;

    public function existsTable(AbstractDatabase $db, string $tableName): bool;
}
