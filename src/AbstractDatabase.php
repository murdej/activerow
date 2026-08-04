<?php

namespace Murdej\ActiveRow;

use Murdej\QueryMaker\Common\Query;
use Murdej\QueryMaker\Maker\BaseMaker;
use Murdej\QueryMaker\Maker\MariaDB;

abstract class AbstractDatabase
{

    public function getEntityByPrimary(TableInfo $tableInfo, int|string $id): ?object {
        $q = new Query();
        $q->columns->addColumn('*');
        $q->conditions->addEq(reset($tableInfo->primary)->columnName, $id);
        $q->fromTable($tableInfo->tableName);
        $q->limitCount = 1;
        $rows = $this->executeQuery($q);
        return (count($rows) > 0)
            ? $this->createEntity($tableInfo, reset($rows))
            : null;
    }

    public abstract function dbExecuteQuery(string $query, array $params): array;

	public abstract function dbBeginTransaction(): bool;

	public abstract function dbCommit(): bool;

	public abstract function dbRollback(): bool;

	public function inTransaction(callable $operations): bool
	{
		
	}

    public function makeSqlMaker(): BaseMaker
    {
        return new MariaDB();
    }

    public function executeQuery(Query $query): array
    {
        $qm = $this->makeSqlMaker();
        $qav = $qm->makeQuery($query);
        return $this->dbExecuteQuery($qav->query, $qav->values);
    }


    public function createEntity(TableInfo $tableInfo, array $row, bool $isNew = false): object
    {
        $className = $tableInfo->className;
        $instance = new $className();
        $dbEntity = new DBEntity($instance, $row, $this, $isNew);
        $instance->dbEntity = $dbEntity;
        if (method_exists($instance, '__init')) $instance->__init();
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