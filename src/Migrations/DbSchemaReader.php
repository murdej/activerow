<?php

namespace Murdej\ActiveRow\Migrations;

use Murdej\ActiveRow\AbstractDatabase;
use Murdej\ActiveRow\Interfaces\DbTypeDriver;
use Murdej\ActiveRow\TableInfo;

class DbSchemaReader
{
    public function __construct(
        protected AbstractDatabase $db,
        protected DbTypeDriver $driver = new MariaDB(),
    ) {
    }

    public function getTableInfo(string $tableName): ?TableInfo
    {
        return $this->driver->loadTableInfo($this->db, $tableName);
    }

    public function existsTable(string $tableName): bool
    {
        return $this->driver->existsTable($this->db, $tableName);
    }
}
