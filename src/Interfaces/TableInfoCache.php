<?php

namespace Murdej\ActiveRow\Interfaces;

use Murdej\ActiveRow\TableInfo;

interface TableInfoCache
{
	/**
	 * Returns a cached TableInfo for $className, or the result of $generator() with it
	 * stored for next time. Implementations should invalidate the cached entry whenever
	 * the entity's own source file changes (see EntityReflexion::getClassFileName()).
	 */
	public function load(string $className, \Closure $generator): TableInfo;
}
