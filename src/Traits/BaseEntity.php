<?php

namespace Murdej\ActiveRow\Traits;

use Murdej\ActiveRow\DBEntity;

trait BaseEntity
{
    public DBEntity $dbEntity;

    public function __get($key)
    {
        return $this->dbEntity->get($key);
    }

    public function __set($key, $value)
    {
        $this->dbEntity->set($key, $value);
    }

    public function __isset($key)
    {
        return $this->dbEntity->isset($key);
    }

    public function save()
    {
        return $this->dbEntity->save();
    }

    /**
     * @param array<string, mixed> $data
     * @return $this
     */
    public function fromArray($data): self
    {
        $this->dbEntity->fromArray($data);
        return $this;
    }
}