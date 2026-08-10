<?php

namespace Murdej\ActiveRow;

use Murdej\ActiveRow\NReflection\ClassType;

/**
 * Parses entity classes (docblock annotations) into TableInfo/ColumnInfo.
 */
class EntityReflexion
{
	/** Type keywords that are never resolved as a class name. */
	protected const PRIMITIVE_TYPES = ['int', 'decimal', 'double', 'float', 'json', 'string', 'bool', 'DateTime', 'autoIncrement'];

	/** @var array<string, array<string, string>> file name => (short class name => FQCN) */
	protected static array $usesCache = [];

	public static function parseTable(TableInfo $ti, string $cn): void
	{
		$ref = new ClassType($cn);
		$anns = $ref->getAnnotations();
		$ti->className = $cn;
		[$ns, $scn] = TableInfo::splitClassName($cn);
		if (!isset($anns['dbTable']) && !isset($anns['property'])) return;
		if (isset($anns['dbTable']) && $anns['dbTable'][0])
		{
			if (is_string($anns['dbTable'][0]))
			{
				$ti->tableName = $anns['dbTable'][0];
			}
			else if ($anns['dbTable'][0] == true)
			{
				$ti->tableName = Convention::deriveTableNameFromClass($ns, $scn);
			}
			else throw new \Exception("Invalid table def $anns[dbTable][0] for entity '$cn'");
		}
		// Výchozí hodnoty
		if (isset($anns['defaultValues']))
		{
			//todo: Další možnosti - jiný název metody, statická property
			$ti->defaults = $cn::defaultValues();
		}

		if (!isset($anns['property'])) throw new \Exception("Must define any property for entity '$cn'");

		$uses = self::getUses($ref->getFileName());

		foreach ($anns['property'] as $pa)
		{
			$ci = new ColumnInfo(null, $ns, $ti);
			self::parseColumn($ci, $pa, $ns, $uses);
			$ti->addColumn($ci);
		}

		if (isset($anns['event']))
		{
			foreach($anns['event'] as $ev)
			{
				$tmp = explode(' ', $ev);
				if (!in_array($tmp[0], Event::allNames())) throw new \Exception("Event $tmp[0] is not a valid event");
				if (count($tmp) == 2)
					$ti->events[$tmp[0]] = $tmp[1];
				else
					$ti->events[$tmp[0]] = $tmp[0];
			}
		}
	}

	// typ[velikost,dec,default](flag,...,!flag) nazev
	public static function parseColumn(ColumnInfo $ci, string|iterable $ann, string $ns, array $uses): void
	{
		if (is_string($ann)) {
			$m1 = null;
			$m2 = null;
			if (
				preg_match('/^([\\\\?A-Za-z_][\\\\0-9A-Za-z_]*)(\\[([0-9]*)(,[0-9]*)?(,[^\\]]*)?\\])? *(\\(([!\\?A-Za-z_0-9,=]*)\\))? \\$([A-Za-z_][0-9A-Za-z_]*)$/', $ann, $m1)
				|| preg_match('/^([\\\\?A-Za-z_][\\\\0-9A-Za-z_]*) \\$([A-Za-z_][0-9A-Za-z_]*) *(\\[([0-9]*)(,[0-9]*)?(,[^\\]]*)?\\])? *(\\(([!\\?A-Za-z_0-9,=]*)\\))?$/', $ann, $m2)
				)
			{
				if ($m1)
				{
					//0, 1,     2,  3,        4,        5,             6,  7       8
					[$_, $type, $_, $typeLen, $typeDec, $defaultValue, $_, $flags, $propertyName] = $m1 + [null, null, null, null, null, null, null, null, null];
				}
				else if ($m2)
				{
					//0, 1,     2,             3,  4,        5,        6,             7   8
					[$_, $type, $propertyName, $_, $typeLen, $typeDec, $defaultValue, $_, $flags] = $m2 + [null, null, null, null, null, null, null, null, null];
				}
				// dump($m);
				$flagList = explode(',', $flags ?: "");
				if (in_array("autoIncrement", $flagList)) $type = "autoIncrement";
				if (in_array("json", $flagList)) $type = "json";
				$ci->propertyName = $ci->columnName = trim($propertyName);
				$ci->type = trim($type);
				if ($ci->type[0] == '?')
				{
					$ci->type = substr($ci->type, 1);
					$ci->nullable = true;
					$flagList[] = "nullable";
				}
				if ($ci->type == "\DateTime") $ci->type = "DateTime";
				if ($ci->type == 'autoIncrement')
				{
					Convention::autoIncrement($ci);
				}
				if (!in_array($ci->type, self::PRIMITIVE_TYPES, true))
				{
					$ci->type = self::resolveClassName($ci->type, $ns, $uses);
				}
				$ci->typeLen = trim($typeLen) ? (int)$typeLen : null;
				$ci->typeDec = strlen(trim($typeDec ?: '')) > 1 ? (int)substr($typeDec, 1) : null;
				$ci->defaultValue = strlen(trim($defaultValue ?: '')) > 1 ? substr($defaultValue, 1) : null;
				if ($ci->defaultValue)
				{
					switch($ci->dbBaseType)
					{
						case 'json':
							switch($ci->defaultValue)
							{
								case 'n':
									$ci->defaultValue = null;
									break;
								case 'l':
								case 'a':
								case 'd':
									$ci->defaultValue = [];
									break;
								case 't':
									$ci->defaultValue = true;
									break;
								case 'f':
									$ci->defaultValue = false;
									break;
								default:
									$ci->defaultValue = json_decode($ci->defaultValue, true);
									break;
							}
							break;
					}
				}
				$flagAlias = [ '?' => 'nullable', 'pk' => 'primary', "index" => "indexed" ];
				foreach($flagList as $flag)
				{
					$flag = trim($flag);
					if (isset($flagAlias[$flag])) $flag = $flagAlias[$flag];
					if ($flag)
					{
						$flagValue = $flag[0] != '!';
						if (!$flagValue) $flag = substr($flag, 1);
						switch($flag)
						{
							case 'unique':
							case 'primary':
							case 'indexed':
							case 'forInsert':
							case 'forUpdate':
							case 'serialize':
							case 'nullable':
							case 'autoIncrement':
							case 'blankNull':
								$ci->$flag = $flagValue;
								break;
							case 'get':
							case 'set':
								$ci->{'use' . ucfirst($flag)} = $flagValue;
								break;
							case 'getset':
								$ci->useGet = $ci->useSet = $flagValue;
								break;
							case 'fk':
								$ci->fkClass = $ci->type;
								//todo: detekovat podle PK druhé tabulky
								$ci->type = 'int';
								$ci->columnName = $ci->propertyName.'Id';
								break;
							case "autoincrement": // pseudotypes
							case "json":
								break;
							default:
								//todo: php>80
								if (str_starts_with($flag, 'type=')) {
									$ci->dbBaseType = substr($flag, 5);
								}
								elseif (str_starts_with($flag, 'dbType=')) {
									$ci->dbType = substr($flag, 7);
								}
								else throw new \Exception("Invalid column flag '$flag', property '{$ci->getPropertyInfo()}'");
								break;
						}
					}
				}
				if ($ci->fkClass !== null && $ci->isVirtual()) throw new \Exception("Column '{$ci->getPropertyInfo()}' cannot combine 'fk' with 'get'/'set'/'getset'.");
			} else throw new \Exception("Invalid column def '$ann', property '{$ci->getPropertyInfo()}'");
		} else {
			foreach($ann as $k => $v)
			{
				if ($k == 'name')
				{
					$ci->propertyName = $v;
					$ci->columnName = $v;
				} else $ci->$k = $v;
			}
		}
	}

	/**
	 * Resolves a possibly short class name to its FQCN, using the file's `use` imports first and
	 * falling back to the current namespace (mirrors how PHP itself resolves unqualified names).
	 */
	public static function resolveClassName(string $name, string $ns, array $uses): string
	{
		if ($name === '') return $name;
		if ($name[0] === '\\') return substr($name, 1);
		if (str_contains($name, '\\')) return $name;
		if (isset($uses[$name])) return $uses[$name];
		return $ns !== '' ? $ns . '\\' . $name : $name;
	}

	/**
	 * @return array<string, string> short class name => FQCN, as imported by `use` statements in the file
	 */
	public static function getUses(string|false $fileName): array
	{
		if (!$fileName || !is_file($fileName)) return [];
		if (!isset(self::$usesCache[$fileName]))
		{
			self::$usesCache[$fileName] = self::parseUses($fileName);
		}

		return self::$usesCache[$fileName];
	}

	protected static function parseUses(string $fileName): array
	{
		$code = file_get_contents($fileName);
		$tokens = token_get_all($code);

		$uses = [];
		$namespace = '';
		$buildingNamespace = false;
		$buildingUse = false;
		$currentUse = '';

		foreach ($tokens as $token) {
			if (is_array($token)) {
				if ($token[0] === T_NAMESPACE) {
					$buildingNamespace = true;
					$namespace = '';
					continue;
				}
				if ($token[0] === T_USE) {
					$buildingUse = true;
					$currentUse = '';
					continue;
				}
			}

			if ($buildingNamespace) {
				if ($token === ';' || $token === '{') {
					$buildingNamespace = false;
				} elseif (is_array($token) && ($token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED)) {
					$namespace .= $token[1];
				}
				continue;
			}

			if ($buildingUse) {
				if ($token === ';' || $token === ',') {
					$buildingUse = false;
					$parts = explode('\\', trim($currentUse));
					$lastName = end($parts);
					$uses[$lastName] = trim($currentUse);
				} elseif (is_array($token) && ($token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED)) {
					$currentUse .= $token[1];
				}
			}
		}

		return $uses;
	}
}
