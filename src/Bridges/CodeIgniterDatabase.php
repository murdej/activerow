<?php

namespace Murdej\ActiveRow\Bridges;

use Murdej\ActiveRow\AbstractDatabase;

class CodeIgniterDatabase extends AbstractDatabase
{

    public function dbExecuteQuery(string $query, array $params): array
    {
    }

    public function insertRow(string $tableName, array $getModifiedDbData)
    {
    }

    public function updateRow(string $tableName, array $getModifiedDbData, array $keys)
    {
    }
}