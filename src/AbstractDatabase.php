<?php

namespace Murdej\ActiveRow;

use Murdej\QueryMaker\Common\Query;
use Murdej\QueryMaker\Maker\MariaDB;

abstract class AbstractDatabase
{

    public function getEntityByPrimary(TableInfo $tableInfo, $id): ?object {
        $q = new Query();
        $q->columns->addColumn('*');
        $q->conditions->addEq(reset($tableInfo->primary), $id);
        $q->from->fromTable($tableInfo->tableName);
        $q->limitCount = 1;
        $rows = $this->executeQuery($q);
        return (count($rows) > 0)
            ? $this->createEntity($tableInfo, reset($rows))
            : null;
    }

    public abstract function dbExecuteQuery(string $query, array $params): array;

    public function executeQuery(Query $query): array
    {
        $qm = new MariaDB();
        $qav = $qm->makeQuery($query);
        return $this->dbExecuteQuery($qav->query, $qav->values);
    }


    public function createEntity(TableInfo $tableInfo, array $row, bool $isNew = false): object
    {
        $className = $tableInfo->className;
        $instance = new $className();
        $dbEntity = new DBEntity($instance, $row, $this, $isNew);
        $instance->dbEntity = $dbEntity;
        if ($isNew) {
            if (method_exists($className, 'dbDefaultValues')) {
                $instance->fromArray($className::dbDefaultValues());
            }
            else if (method_exists($instance, 'dbDefaultValues')) {
                $instance->fromArray($instance->dbDefaultValues());
            }
        }

        return $instance;
    }

    public abstract function insertRow(string $tableName, array $getModifiedDbData)/*: mixed */;

    public abstract function updateRow(string $tableName, array $getModifiedDbData, array $keys);
}