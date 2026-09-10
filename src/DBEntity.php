<?php

namespace Murdej\ActiveRow;

use Murdej\ActiveRow\NReflection\ClassType;

class DBEntity
{
    public static mixed $globalEventHandler = null;

    public array $src;

    public object $entity;

    public array $modified = [];

    public array $converted = [];

    public array $defaults = [];

    public bool $isNew = false;

    // public $collection = null;

    public AbstractDatabase $database;

    public function get(string $col)
    {
        $dbi = $this->getDbInfo();
        // properties
        if ($dbi->existsCol($col))
        {
            if (!array_key_exists($col, $this->converted))
            {
                $this->converted[$col] = $this->convertFromSrc($col);
            }

            return $this->converted[$col];
        }
        //todo: related

        $reflexion = new ClassType(get_class($this->entity));
        $uname = ucfirst($col);
        do
        {
            $methodName = 'get' . $uname;
            if ($reflexion->hasMethod($methodName)) break;

            $methodName = 'is' . $uname;
            if ($reflexion->hasMethod($methodName)) break;

            throw new \Exception("Property $dbi->className::$col is not defined.");
        } while(false);
        return $this->entity->$methodName();
    }

    public function isset(string $col): bool
    {
        $dbi = $this->getDbInfo();
        if ($dbi->existsCol($col) || $dbi->existsRelated($col)) return true;

        $reflexion = new ClassType(get_class($this->entity));
        $uname = ucfirst($col);
        $methodName = 'get' . $uname;
        if ($reflexion->hasMethod($methodName)) return true;

        $methodName = 'is' . $uname;
        if ($reflexion->hasMethod($methodName)) return true;

        return false;
    }

    public function set(string $col, mixed $value)
    {
        $dbi = $this->getDbInfo();
        if ($dbi->existsCol($col))
        {
            $colDef = $dbi->columns[$col];
            if ($colDef->blankNull && !$value) $value = null;
            if ($colDef->fkClass && $col == $colDef->propertyName)
                throw new \Exception("Cannot replace fk object $col.");
            // Strict !== — a loose != treats null as equal to 0/false/''/'0', which silently drops a
            // real change (e.g. a bool column going from an existing true(1)/unset(null) value to
            // false(0)) since the "already equal, nothing to do" branch below then never marks the
            // column modified nor writes it. Found via App\Services\Scripting\
            // RunningProcParamValueApplierService in the fluxus project: RunningProcParam::$intValue
            // going from 1 -> null (apply()'s own clear step) -> 0 silently stayed null forever,
            // because `null != 0` is false in PHP.
            if (!array_key_exists($col, $this->converted) || $this->converted[$col] !== $value)
            {
                $this->converted[$col] = $value;
                if (!in_array($col, $this->modified)) $this->modified[] = $col;
                if ($colDef->fkClass)
                    unset($this->converted[$colDef->propertyName]);
            }
        }
        else
        {
            $reflexion = new ClassType(get_class($this->entity));
            $uname = ucfirst($col);

            $methodName = 'set' . $uname;
            if (!$reflexion->hasMethod($methodName)) throw new \Exception("Column '$col' is not defined in class '".$dbi->className."'.");

            $this->entity->$methodName($value);
        }
    }

    public function getDbInfo(?string $className = null): TableInfo
    {
        if (!$className) $className = get_class($this->entity);
        return TableInfo::get($className);
    }

    public function convertFromSrc(string $col)
    {
        $val = null;
        if (array_key_exists($col, $this->src))
        {
            $val = $this->src[$col];
        }
        else
        {
            if (array_key_exists($col, $this->defaults))
            {
                $val = $this->defaults[$col];
            } else {
                $colDef = $this->getDbInfo()->columns[$col];
                if ($colDef->fkClass && $col == $colDef->propertyName)
                {
                    $fkValue = $this->get($colDef->columnName);
                    if ($fkValue === null) return null;
                    $className = $colDef->fkClass;
                    return $this->database->getEntityByPrimary(TableInfo::get($className), $fkValue);
                }
                else if (!$colDef->nullable)
                {
                    // Default hodnoty nenull primitivních typů
                    return Converter::get()->getDefaultOfType($colDef->type);
                }
            }
        }

        return Converter::get()->convertTo($val, $this->getDbInfo()->columns[$col], $col, $this);
    }

    public function __construct(object $entity, array $src, AbstractDatabase $database, bool $isNew = false)
    {
        $this->entity = $entity;
        $this->src = $src;
        $this->defaults = &$this->getDbInfo()->defaults;
        $this->database = $database;
        $this->isNew = $isNew;
    }

    public function getModifiedDbData(bool $forInsert = false): array
    {
        $res = [];
        // přímo modifikované
        foreach($this->modified as $col)
        {
            $colInfo = $this->getDbInfo()->columns[$col];
            $res[$colInfo->columnName] = Converter::get()->convertFrom($this->converted[$col], $this->getDbInfo()->columns[$col]);
        }
        foreach($this->getDbInfo()->dbColumns as $col => $colInfo)
        {
            // serializované
            if ($colInfo->serialize && array_key_exists($col, $this->converted))
            {
                $dbValue = Converter::get()->convertFrom($this->converted[$col], $colInfo);
                // Same !== fix as set() above — both sides are already the serialized (string) DB
                // representation here, so strict comparison is safe and avoids the same null/0/''
                // false-negative class of bug.
                if (!isset($this->src[$col]) || $dbValue !== $this->src[$col])
                    $res[$colInfo->columnName] = $dbValue;
            }
            if ($forInsert)
            {
                // Pro insert i default hodnoty
                if (!in_array($col, $this->modified)
                    && ($colInfo->defaultValue !== null || array_key_exists($col, $this->getDbInfo()->defaults)))
                {
                    if (!array_key_exists($col, $this->converted)) $this->get($col);
                    $res[$colInfo->columnName] = Converter::get()->convertFrom($this->converted[$col], $this->getDbInfo()->columns[$col]);
                }
            }
        }

        return $res;
    }

    public function save()
    {
        $ti = $this->getDbInfo();
        if ($this->isNew) {
            $pkCol = reset($ti->primary);

            $event = new Event(Event::beforeSave);
            $this->callEvent($event);
            $event->event = Event::beforeInsert;
            $this->callEvent($event);
            $event->event = Event::prepareDbData;
            $event->data = $this->getModifiedDbData(true);
            $this->callEvent($event);


            $newId = $this->database->insertRow(
                $ti->tableName,
                $event->data,
            );
            if ($pkCol && $pkCol->autoIncrement) {
                $this->src[$pkCol->propertyName] = $newId;
            }
            if ($pkCol) {
                $pkValue = $this->src[$pkCol->propertyName] ?? null;
                if ($pkValue !== null) {
                    $freshEntity = $this->database->getEntityByPrimary($ti, $pkValue);
                    if ($freshEntity) $this->src = $freshEntity->dbEntity->src;
                }
            }
            $this->isNew = false;
            $this->converted = [];
            $this->modified = [];

            $event->insertId = $newId;
            $event->event = Event::afterInsert;
            $this->callEvent($event);
            $event->event = Event::afterSave;
            $this->callEvent($event);

            return true;
        } else {
            $keys = [];
            if (count($this->modified) == 0) return false;
            foreach ($ti->primary as $column) $keys[$column->columnName] = $this->get($column->propertyName);
            if (!$keys) throw new \Exception("No primary key defined, cannot save.");

            $event = new Event(Event::beforeSave, null, null, $keys);
            $this->callEvent($event);
            $event->event = Event::beforeUpdate;
            $this->callEvent($event);

            $event->event = Event::prepareDbData;
            $event->data = $this->getModifiedDbData(false);
            $this->callEvent($event);

            $this->database->updateRow(
                $ti->tableName,
                $event->data,
                $keys,
            );
            $pkCol = reset($ti->primary);
            if ($pkCol) {
                $freshEntity = $this->database->getEntityByPrimary($ti, reset($keys));
                if ($freshEntity) $this->src = $freshEntity->dbEntity->src;
            }
            $this->converted = [];
            $this->modified = [];

            $event->event = Event::afterUpdate;
            $this->callEvent($event);
            $event->event = Event::afterSave;
            $this->callEvent($event);

            return true;
        }
    }

    protected function callEvent(Event $event)
    {
        $r = null;
        if ($eventMethod = $this->getDbInfo()->events[$event->event] ?? null) {
            $r = $this->entity->{$eventMethod}($event);
        }
        if (DBEntity::$globalEventHandler) {
            (DBEntity::$globalEventHandler)($event);
        }
        return $r;
    }

    public function fromArray(array $data)
    {
        foreach ($this->getDbInfo()->columns as $colInfo) {
            if (array_key_exists($colInfo->propertyName, $data)) $this->set($colInfo->columnName, $data[$colInfo->propertyName]);
        }
    }

    public function toArray(?array $cols = null, bool $fkObjects = false, string $prefix = '')
    {
        $dbi = $this->getDbInfo();
        if (!$cols) $cols = array_keys($dbi->columns);
        $res = [];
        foreach ($cols as $col)
        {
            $colDef = $dbi->columns[$col];
            if (!$fkObjects && $colDef->fkClass && $col == $colDef->propertyName) continue;
            $res[$prefix.$col] = $this->get($col);
        }

        return $res;
    }

}