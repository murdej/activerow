<?php

namespace Murdej\ActiveRow;

use Murdej\QueryMaker\Common\Query;

/**
 * @template T
 */
class DBRepository
{

    protected TableInfo $tableInfo;

    /**
     * @throws \Exception
     */
    public function __construct(
		protected AbstractDatabase $database,
	    protected ?string $className = null,
	)
    {
        if (!$this->className) throw new \Exception("Table name not specified");
        $this->tableInfo = TableInfo::get($this->className);
    }

    /**
     * @return DBSelect|T[]
     */
    public function newSelect(): DBSelect
    {
        return new DBSelect($this->database, $this->tableInfo);
    }

    /**
     * Return entity by primary id
     * @param $id
     * @return T|null
     */
    public function get(int|string|null $id): ?object
    {
        if ($id === null) return null;
        return $this->database->getEntityByPrimary($this->tableInfo, $id);
    }

    /**
     * Return entity by primary id
     * @param $id
     * @return T|null
     */
    public function getBy(array $conditions): ?object
    {
        $dbs = $this->newSelect();
        $dbs->where($conditions);

        return $dbs->fetchEntity();
    }

    /**
     * @return DBSelect|T[]
     */
    public function findBy(array $conditions): DBSelect
    {
        $dbs = $this->newSelect();
        $dbs->where($conditions);

        return $dbs;
    }

    /**
     * @return DBSelect|T[]
     */
    public function findAll(): DBSelect
    {
        return $this->findBy([]);
    }

    /**
     * @param array<string,mixed> $initData
     * @return T
     */
    public function newEntity(array $initData = []): object
    {
        return $this->database->createEntity($this->tableInfo, $initData, true);
    }
}