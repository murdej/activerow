<?php

namespace Murdej\ActiveRow\Bridges;

use Murdej\ActiveRow\AbstractDatabase;
use Nette\Database\Explorer;

class NetteDatabase extends AbstractDatabase
{

    public function __construct(
        private Explorer $explorer,
    ) {
    }

    public function dbExecuteQuery(string $query, array $params): array
    {
        $rows = [];
        foreach ($this->explorer->queryArgs($query, $params) as $row) {
            $rows[] = (array) $row;
        }
        return $rows;
    }

    public function insertRow(string $tableName, array $getModifiedDbData)/*: mixed */
    {
        $this->explorer->query('INSERT INTO ?name ?', $tableName, $getModifiedDbData);
        return $this->explorer->getInsertId();
    }

    public function updateRow(string $tableName, array $getModifiedDbData, array $keys)
    {
        $this->explorer->query('UPDATE ?name SET ? WHERE ?', $tableName, $getModifiedDbData, $keys);
    }
}
