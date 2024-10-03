<?php

namespace Murdej\ActiveRow;

class DBEntity
{
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
        throw new \Exception("Property $dbi->className::$col is not defined.");
        //todo getter, setter, related
        /* else if ($dbi->existsRelated($col))
        {
            $ri = $dbi->relateds[$col];
            if (is_array($this->src))
            {
                $items = false;
            }
            else
            {
                // Háže tam nesmyslnou podmínku a vrátí h*vno
                //$items = $this->src->related($ri->relTableName, $ri->relColumn);
                // Pomalejší ale funkční
                $cn = $ri->relClass;
                $pkCol = reset($dbi->primary)->columnName;
                if (!$pkCol) throw new \Exception("Entity '$dbi->className' has not primary column.");
                return $cn::findBy([(
                $ri->relColumn ?: $dbi->tableName.'Id'
                ) => $this->get($pkCol)]);
            }
            $sel = new DBSelect(
                new DBRepository($ri->relClass),
                $items
            );
            return $sel;
        }
        else
        {
            $reflexion = new ClassType(get_class($this->entity));
            $uname = ucfirst($col);
            do
            {
                $methodName = 'get' . $uname;
                if ($reflexion->hasMethod($methodName)) break;

                $methodName = 'is' . $uname;
                if ($reflexion->hasMethod($methodName)) break;

                throw new \Exception("Property $reflexion->name::$col is not defined.");
            } while(false);
            return $this->entity->$methodName();
        } */

    }

    public function isset($col): bool
    {
        $dbi = $this->getDbInfo();
        if ($dbi->existsCol($col) || $dbi->existsRelated($col)) return true;

        //todo: getter, setter
        /* $reflexion = new ClassType(get_class($this->entity));
        $uname = ucfirst($col);
        $methodName = 'get' . $uname;
        if ($reflexion->hasMethod($methodName)) return true;

        $methodName = 'is' . $uname;
        if ($reflexion->hasMethod($methodName)) return true; */

        return false;
    }

    public function set($col, $value)
    {
        $dbi = $this->getDbInfo();
        if ($dbi->existsCol($col))
        {
            $colDef = $dbi->columns[$col];
            if ($colDef->blankNull && !$value) $value = null;
            if ($colDef->fkClass && $col == $colDef->propertyName)
                throw new \Exception("Cannot replace fk object $col.");
            if (!array_key_exists($col, $this->converted) || $this->converted[$col] != $value)
            {
                $this->converted[$col] = $value;
                if (!in_array($col, $this->modified)) $this->modified[] = $col;
                if ($colDef->fkClass)
                    unset($this->converted[$colDef->propertyName]);
            }
        }
        //todo: getter, setter
        /* else
        {
            $reflexion = new ClassType(get_class($this->entity));
            $uname = ucfirst($col);

            $methodName = 'set' . $uname;
            if (!$reflexion->hasMethod($methodName)) throw new \Exception("Column '$col' is not defined in class '".$dbi->className."'.");

            $this->entity->$methodName($value);
        } */
        // else throw new \Exception("Column $col is not defined.");
    }

    public function getDbInfo($className = null): TableInfo
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
                    $className = $colDef->fkClass;
                    return $this->database->getEntityByPrimary($className, $this->get($colDef->columnName));
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
        foreach($this->getDbInfo()->columns as $col => $colInfo)
        {
            // serializované
            if ($colInfo->serialize && array_key_exists($col, $this->converted))
            {
                $dbValue = Converter::get()->convertFrom($this->converted[$col], $colInfo);
                if (!isset($col, $this->src) || $dbValue != $this->src[$col])
                    $res[$colInfo->columnName] = $dbValue;
            }
            if ($forInsert)
            {
                // Pro insert i default hodnoty
                if ($colInfo->defaultValue !== null && !in_array($col, $this->modified))
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
            $event->data = $this->getModifiedDbData(true);
            $this->callEvent($event);

            $this->database->updateRow(
                $ti->tableName,
                $event->data,
                $keys,
            );
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
        if ($eventMethod = $this->getDbInfo()->events[$event->event] ?? null) {
            return $this->entity->{$eventMethod}($event);
        }
        return null;
    }

    public function fromArray(array $data)
    {
        foreach ($this->getDbInfo()->columns as $colInfo) {
            if (isset($data[$colInfo->propertyName])) $this->set($colInfo->columnName, $data[$colInfo->propertyName]);
        }
    }

}