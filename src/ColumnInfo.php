<?php

namespace  Murdej\ActiveRow;

/**
 * @property string $fullName
 * @property string $propertyInfo
 */
class ColumnInfo implements \JsonSerializable // extends \Nette\Object
{
	public string $columnName = '';

	public string $propertyName = '';

	public string $type;

	public ?int $typeLen = null;

	public ?int $typeDec = null;

	public mixed $defaultValue = null;

	public bool $unique = false;

	public bool $primary = false;

	public bool $indexed = false;

	public bool $forInsert = true;

	public bool $nullable = false;

	public bool $blankNull = false;

	public bool $forUpdate = true;

	public ?string $fkClass = null;

	public ?string $fkTable = null;

	public bool $autoIncrement = false;

	public bool $serialize = false;

	public ?TableInfo $tableInfo = null;

    public ?string $dbType = null;

    public ?string $dbBaseType = null;

    public ?string $liveType = null;

	public bool $useGet = false;

	public bool $useSet = false;

    public function getFullName(): string
	{
		return $this->propertyName;
	}

	// typ[velikost,dec,default](flag,...,!flag) nazev
	public function parseAnnotation(string|iterable $ann, string $ns)
	{
		EntityReflexion::parseColumn($this, $ann, $ns, []);
	}

	public function getPropertyInfo(): string
	{
		return $this->tableInfo->className."::".$this->propertyName;
	}

	public function __construct(string|iterable|null $ann, ?string $ns, TableInfo $tableInfo)
	{
		$this->tableInfo = $tableInfo;
		if ($ann !== null) $this->parseAnnotation($ann, $ns);
	}

	public static array $config = [
		'namingConvence' => [
			'fkSuffix' => 'Id',
		]
	];

	public static function getLength(string $className, string $columnName) : ?int {
		$ti = TableInfo::get($className);
		return $ti->columns[$columnName]->typeLen;
	}

	public function getFkTableInfo() : ?TableInfo
	{
		return $this->fkClass ? TableInfo::get($this->fkClass) : null;
	}

	public function isVirtual(): bool
	{
		return $this->useGet || $this->useSet;
	}

	public function jsonSerialize(): array
	{
		return [
			'columnName' => $this->columnName,
			'propertyName' => $this->propertyName,
			'type' => $this->type ?? null,
			'typeLen' => $this->typeLen,
			'typeDec' => $this->typeDec,
			'defaultValue' => $this->defaultValue,
			'unique' => $this->unique,
			'primary' => $this->primary,
			'indexed' => $this->indexed,
			'forInsert' => $this->forInsert,
			'nullable' => $this->nullable,
			'blankNull' => $this->blankNull,
			'forUpdate' => $this->forUpdate,
			'fkClass' => $this->fkClass,
			'fkTable' => $this->fkTable,
			'autoIncrement' => $this->autoIncrement,
			'serialize' => $this->serialize,
			'dbType' => $this->dbType,
			'dbBaseType' => $this->dbBaseType,
			'liveType' => $this->liveType,
			'useGet' => $this->useGet,
			'useSet' => $this->useSet,
		];
	}

	public static function fromArray(array $data, TableInfo $tableInfo): self
	{
		$ci = new self(null, null, $tableInfo);
		foreach ($data as $key => $value)
		{
			if ($key === 'type' && $value === null) continue;
			if (property_exists($ci, $key)) $ci->$key = $value;
		}

		return $ci;
	}

	public static function fromJson(string $json, TableInfo $tableInfo): self
	{
		return self::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR), $tableInfo);
	}
}
