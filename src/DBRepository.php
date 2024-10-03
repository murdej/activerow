<?php

namespace Murdej\ActiveRow;

use Murdej\QueryMaker\Common\Query;

/**
 * @template T
 */
abstract class DBRepository
{
    protected AbstractDatabase $database;

    protected TableInfo $tableInfo;

    /**
     * @var string
     * @abstract
     */
    protected ?string $className = null;

    /**
     * @throws \Exception
     */
    public function __construct(AbstractDatabase $database)
    {
        if (!$this->className) throw new \Exception("Table name not specified");
        $this->database = $database;
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
    public function get($id): ?object
    {
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