<?php

namespace  Murdej\ActiveRow;

class Convention
{
	public static function deriveTableNameFromClass(string $ns, string $scn)
	{
		/*$p = strrpos($cn, '\\');
		if ($p < 0) $p = 0;
		else $p++;
		return lcfirst(substr($cn, $p));*/
		return lcfirst($scn);
	}

	public static function autoIncrement(ColumnInfo $ci)
	{
		$ci->type = 'int';
		$ci->primary = true;
		$ci->autoIncrement = true;
		$ci->forInsert = false;
	}
}
