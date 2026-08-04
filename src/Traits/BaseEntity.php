<?php

namespace Murdej\ActiveRow\Traits;

use Murdej\ActiveRow\Converter;
use Murdej\ActiveRow\DBEntity;

trait BaseEntity
{
    public DBEntity $dbEntity;

    public function __get(string $key)
    {
        return $this->dbEntity->get($key);
    }

    public function __set(string $key, mixed $value)
    {
        $this->dbEntity->set($key, $value);
    }

    public function __isset(string $key)
    {
        return $this->dbEntity->isset($key);
    }

    public function __init()
    {
    }

    public function save()
    {
        return $this->dbEntity->save();
    }

    public function toArray(?array $cols = null, bool $fkObjects = false, string $prefix = ''): array
    {
        return $this->dbEntity->toArray($cols, $fkObjects, $prefix);
    }

    /**
     * @param array<string, mixed>|\ArrayAccess $values
     * @param array|null $cols
     * @param array|null $ignoreCols
     * @return $this
     */
    public function fromArray(array|\ArrayAccess $values, ?array $cols = null, array $ignoreCols = ['id'], bool $autoConvert = false): self
    {
        $converter = $autoConvert ? new Converter() : null;
        $dbInfo = $this->dbEntity->getDbInfo();
        $columnNames = $dbInfo->getColumnNames();
        foreach ($values as $key => $value) {
            if (in_array($key, $columnNames)
                && ($cols === null || in_array($key, $cols))
                && ($ignoreCols === null || !in_array($key, $ignoreCols))
            ) {
                if ($converter && ($dbInfo->columns[$key] ?? false)) {
                    $value = $converter->convertTo($value, $dbInfo->columns[$key], $key, $this->dbEntity);
                }
                $this->$key = $value;
            }
        }

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}