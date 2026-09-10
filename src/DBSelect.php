<?php

namespace Murdej\ActiveRow;

use Murdej\QueryMaker\Common\ColumnCollection;
use Murdej\QueryMaker\Common\Identifier;
use Murdej\QueryMaker\Common\OrderCollection;
use Murdej\QueryMaker\Common\Query;

class DBSelect implements \Iterator, \Countable
{
    protected AbstractDatabase $database;

    protected TableInfo $tableInfo;

    public Query $query;

    public function __construct(AbstractDatabase $database, TableInfo|string $table)
    {
        $this->database = $database;
        $this->tableInfo = $table instanceof TableInfo ? $table : TableInfo::get($table);
        $this->query = new Query();
        $this->query->fromTable($this->tableInfo->tableName);
    }

    protected ?array $result = null;

    // #[\ReturnTypeWillChange] on all 5 — \Iterator declares current()/key(): mixed, valid(): bool,
    // next()/rewind(): void, but next() here deliberately RETURNS the entity (mimicking the native
    // current()/next()/key() array-cursor functions this class wraps), which is incompatible with
    // `: void` — adding a real `: void` return type would be a fatal "must not return a value"
    // error. The attribute suppresses PHP 8.1+'s Iterator-signature-mismatch deprecation without
    // changing any of these methods' actual behavior.
    #[\ReturnTypeWillChange]
    public function current()
    {
        $this->fetchResultIfNeed();
        return $this->createEntity(current($this->result));
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->fetchResultIfNeed();
        return $this->createEntity(next($this->result));
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        $this->fetchResultIfNeed();
        return key($this->result);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        $this->fetchResultIfNeed();
        return key($this->result) !== null;
    }

    #[\ReturnTypeWillChange]
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
    public function where(mixed $a): self
    {
        $this->query->conditions->addMulti(is_array($a) ? $a : [$a]);
        return $this;
    }

    /**
     * Add selected column
     * @param ...$columns
     * @return $this
     */
    public function select(string|Identifier ...$columns): self
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
    public function order(string|Identifier ...$columns): self
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

    /**
     * @param string|callable|null $key
     * @param string|callable|null $value
     * @return array<mixed,mixed>
     */
    public function fetchPairs(string|callable|null $key, string|callable|null $value = null): array
    {
        $res = [];
        foreach ($this as $row) {
            if ($key) {
                $k = $this->getColValue($row, $key);
                $res[$k] = $value ? $this->getColValue($row, $value) : $row;
            } else {
                $res[] = $value ? $this->getColValue($row, $value) : $row;
            }
        }

        return $res;
    }

    protected function getColValue(object $row, string|callable $col)
    {
        if (is_string($col)) return $row->$col;
        if (is_callable($col)) return $col($row);
        throw new \Exception('Column must be string or callable');
    }

    public function fetchField(int|string|null $field = null)
    {
        $this->fetchResultIfNeed();
        $row = reset($this->result);
        if ($row === false) return null;
        if ($field !== null) return $row[$field] ?? null;

        return reset($row);
    }

    public function fetchArray(bool $rowIsArray = false): array
    {
        $res = [];
        foreach ($this as $row) {
            if ($rowIsArray) $row = $row->toArray();
            $res[] = $row;
        }

        return $res;
    }

    /**
     * @return array<string,mixed>[]
     */
    public function fetchArrayWithExtraFields(): array
    {
        $res = [];
        foreach ($this as $entity) {
            $row = $entity->dbEntity->src;
            foreach ($entity->toArray() as $k => $v) $row[$k] = $v;
            $res[] = $row;
        }

        return $res;
    }

    public function count(): int
    {
        $countQuery = clone $this->query;
        $countQuery->columns = new ColumnCollection($countQuery);
        $countQuery->columns->addSnippet('cnt')->code('COUNT(*)');
        $countQuery->orders = new OrderCollection($countQuery);
        $countQuery->limitCount = null;
        $countQuery->limitFrom = 0;

        $rows = $this->database->executeQuery($countQuery);
        return (int) ($rows[0]['cnt'] ?? 0);
    }
}