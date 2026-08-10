<?php

namespace Murdej\ActiveRow;

use Murdej\ActiveRow\NReflection\ClassType;

class TableInfo implements \JsonSerializable
{
	public string $tableName;
	
	public string $className;

	/** @var ColumnInfo[] */
	public array $primary = [];
	
	public array $fkColumns = [];
	
	/** @var ColumnInfo[] */
	public array $columns = [];

	/** @var ColumnInfo[] Subset of $columns actually persisted to the database (excludes get/set-only virtual columns). */
	public array $dbColumns = [];

	public array $defaults = [];
	
	// public $relateds = [];
	
	// public $defaultOrder = null;

    /**
     * @var array<string, string>
     */
	public array $events = [];
	
	public function parseClass(string $cn)
	{
		EntityReflexion::parseTable($this, $cn);
	}

	public function addColumn(ColumnInfo $ci): void
	{
		$this->columns[$ci->propertyName] = $ci;
		if ($ci->fkClass)
		{
			$this->columns[$ci->columnName] = $ci;
		}
		if (!$ci->isVirtual())
		{
			$this->dbColumns[$ci->propertyName] = $ci;
			if ($ci->fkClass) $this->dbColumns[$ci->columnName] = $ci;
		}
		if ($ci->primary) $this->primary[$ci->propertyName] = $ci;
		if ($ci->defaultValue) $this->defaults[$ci->propertyName] = $ci->defaultValue;
	}

	public function existsCol(string $col)
	{
		return isset($this->dbColumns[$col]) || isset($this->fkColumns[$col]);
	}

	public function existsRelated(string $col)
	{
		return isset($this->relateds[$col]);
	}

	public function __construct(?string $cn)
	{
		if ($cn) $this->parseClass($cn);
	}

	protected static array $dbInfoCache = [];

	public static function get(string $className) : TableInfo
	{
		if (!isset(self::$dbInfoCache[$className]))
		{
			self::$dbInfoCache[$className] = new TableInfo($className);
		}
		
		return self::$dbInfoCache[$className];
	}

    public static function tableName(string $className) : string
    {
        self::get($className)->tableName;
    }

    public static function getFullClassName(string $className, string $nameSpace)
	{
		return (strpos($className, '\\') == false)
			? $nameSpace.'\\'.$className
			: $className;
	}

	public static function splitClassName(string $className)
	{
		$p = strrpos($className, '\\');
		return ($p === false)
			? ['', $className]
			: [substr($className, 0, $p), substr($className, $p + 1)];
	}

	protected ?array $_columnNames = null;

	public function getColumnNames(?bool $inDb = null)
	{
		if ($this->_columnNames === null)
		{
			$this->_columnNames = [];
			foreach($this->columns as $colName => $col)
			{
				// dump($col);
                if ($inDb === null || ($inDb === isset($this->dbColumns[$colName]))) {
                    $this->_columnNames[] = $colName; //$col->propertyName;
                    // if ($col->fkClass) $this->_columnNames[] = $col->propertyName;
                }
			}
		}

		return $this->_columnNames;
	}

	public function jsonSerialize(): array
	{
		$columns = [];
		foreach ($this->columns as $propertyName => $col)
		{
			if ($propertyName !== $col->propertyName) continue; // skip fk alias entries keyed by columnName
			$columns[$propertyName] = $col->jsonSerialize();
		}

		return [
			'className' => $this->className ?? null,
			'tableName' => $this->tableName ?? null,
			'columns' => $columns,
			'defaults' => $this->defaults,
			'events' => $this->events,
		];
	}

	public static function fromArray(array $data): self
	{
		$ti = new self(null);
		if (isset($data['className'])) $ti->className = $data['className'];
		if (isset($data['tableName'])) $ti->tableName = $data['tableName'];
		$ti->defaults = $data['defaults'] ?? [];
		$ti->events = $data['events'] ?? [];
		foreach ($data['columns'] ?? [] as $colData)
		{
			$ti->addColumn(ColumnInfo::fromArray($colData, $ti));
		}

		return $ti;
	}

	public static function fromJson(string $json): self
	{
		return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
	}

	public function toJson(int $flags = 0): string
	{
		return json_encode($this, $flags | JSON_THROW_ON_ERROR);
	}
}
