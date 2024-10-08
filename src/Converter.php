<?php

namespace  Murdej\ActiveRow;

class Converter
{
	public static function get(): Converter
	{
		return new Converter();
	}
	
	// Konverze z DB do Ent
	public function convertTo($value, $columnInfo, $col, DBEntity $dbEntity)
	{
		if ($columnInfo->serialize)
		{
			$className = $columnInfo->type;
			$obj = new $className();
			$obj->fromDbValue($value);
			
			return $obj;
		}
		if ($value === null)
		{
			if ($columnInfo->nullable) return null;
			// u autoIncrement je načtení hodnoty null povoleno aby bylo možné testovat jestli je objekt nový
			if ($columnInfo->autoIncrement) return null;
			// u fk je načtení hodnoty null povoleno aby bylo možné testovat jestli je nastaveno
			if ($columnInfo->fkClass) return null;
			// pokud je default hodnota nastav
			if ($columnInfo->defaultValue !== null) return $columnInfo->defaultValue;
			 
			throw new \Exception("Column ".($columnInfo->tableInfo ? $columnInfo->tableInfo->className.'::' : '')."$columnInfo->fullName is not nullable");
		}
		if ($columnInfo->fkClass && $col == $columnInfo->propertyName) 
		{
			$cls = $columnInfo->fkClass;
            //todo: nestatické repo
            if (method_exists($cls, 'repository')) {
			    return $cls::repository()->createEntity($value);
            } else {
                $repo = new DBRepository($cls, $dbEntity->db);
                return $repo->createEntity($value);
            }
		}
		else 
		{
			switch($columnInfo->type)
			{
				case 'int':
					return (int)$value;
				case 'decimal':
					return (double)$value;
				case 'double':
					return (double)$value;
				case 'float':
					return (float)$value;
				case 'json':
					return is_array($value) ? $value : json_decode($value, true);
				case 'string':
					return (string)$value;
				case 'bool':
					switch(true)
					{
						case $value === 0:
						case $value === '':
						case $value === 'false':
						case $value === 'no':
						case $value === 'off':
						case $value === "\x00":
						case $value === false;
						case $value === '0';
							return false;
						case $value === 1:
						case $value === 'true':
						case $value === 'yes':
						case $value === 'on':
						case $value === "\x01":
						case $value === true:
						case $value === '1';
							return true;
						default:
							throw new \Exception("Invalid bool value $value");
					}
				case 'DateTime':
				case "\\DateTime":
					/*$dt = new \DateTime();
					$dt->setTimestamp((int)$value);
					return $dt; */
					return $value;
				default:
					throw new \Exception("Unknown type ".($columnInfo->tableInfo ? $columnInfo->tableInfo->className.'::' : '')."$columnInfo->type");
					
			}
		}
	}

	// Konverze z entity do DB
	public function convertFrom($value, $columnInfo/*, $dbEntity */)
	{
		if ($value === null) return null;
		if ($columnInfo->serialize)
		{
			return $value->toDbValue();
		} 
		else
		{
			switch($columnInfo->type)
			{
				case 'json':
					return json_encode($value);
				case 'DateTime':
					return $value; // $value->getTimestamp();
				case 'int':
					return $value === '' ? null : (int)$value;
                case 'float':
                    return $value === '' ? null : floatval($value);
			}
			return $value;
		}
	}
	
	public function getDefaultOfType($type)
	{
		switch($type)
		{
			case 'int':
				return 0;
			case 'decimal':
			case 'double':
			case 'float':
				return 0.0;
			case 'string':
				return '';
			case 'bool':
				return false;
		}
	}
}