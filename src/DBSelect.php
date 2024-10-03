<?php

namespace Murdej\ActiveRow;

use Murdej\QueryMaker\Common\Query;

class DBSelect implements \Iterator
{
    /**
     * @param AbstractDatabase $database
     * @param TableInfo|string $table
     */

    protected AbstractDatabase $database;

    protected TableInfo $tableInfo;

    public Query $query;

    public function __construct(AbstractDatabase $database, $table)
    {
        $this->database = $database;
        $this->tableInfo = $table instanceof TableInfo ? $table : TableInfo::get($table);
        $this->query = new Query();
        $this->query->fromTable($this->tableInfo->tableName);
    }

    protected ?array $result = null;

    public function current()
    {
        $this->fetchResultIfNeed();
        return $this->createEntity(current($this->result));
    }

    public function next()
    {
        $this->fetchResultIfNeed();
        return $this->createEntity(next($this->result));
    }

    public function key()
    {
        $this->fetchResultIfNeed();
        return key($this->result);
    }

    public function valid()
    {
        $this->fetchResultIfNeed();
        return key($this->result) !== null;
    }

    public function rewind()
    {
        $this->fetchResultIfNeed();
        reset($this->result);
    }

    private function fetchResultIfNeed(): void
    {
        if ($this->result === null) {
            $this->result = $this->database->executeQuery($this->query);
        }
    }

    /**
     * Add conditions
     * @param $a
     * @return $this
     */
    public function where($a): self
    {
        $this->query->conditions->addMulti(is_array($a) ? $a : [$a]);
        return $this;
    }

    /**
     * Add selected column
     * @param ...$columns
     * @return $this
     */
    public function select(...$columns): self
    {
        foreach ($columns as $column) {
            $this->query->columns->addColumn($column);
        }

        return $this;
    }

    /**
     * Add order columns
     * @param ...$columns
     * @return $this
     */
    public function order(...$columns): self
    {
        foreach ($columns as $column) {
            $this->query->orders->addColumn($column);
        }

        return $this;
    }

    /**
     * Set limit and offset
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->query->limitCount = $limit;
        $this->query->limitFrom = $offset;

        return $this;
    }

    public function fetchEntity(): ?object
    {
        $ent = $this->current();
        $this->next();
        return $ent;
    }

    public function fetchRow(): ?array
    {
        $ent = $this->current();
        $this->next();
        return $ent ? $ent->dbEntity->src : null;
    }

    /**
     * Create new empty entity
     * @param array|false $row
     * @return object|mixed|null
     */
    protected function createEntity(array|false $row): ?object {
        return $row ? $this->database->createEntity($this->tableInfo, $row) : null;
    }

    /**
     * @return array
     */
    public function fetchEntities(): array
    {
        $res = [];
        foreach ($this as $entity) $res[] = $entity;

        return $res;
    }

    public function fetchRows(): array
    {
        $res = [];
        foreach ($this as $entity) $res[] = $entity->dbEntity->src;

        return $res;
    }
}