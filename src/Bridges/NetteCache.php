<?php

namespace Murdej\ActiveRow\Bridges;

use Murdej\ActiveRow\EntityReflexion;
use Murdej\ActiveRow\Interfaces\TableInfoCache;
use Murdej\ActiveRow\TableInfo;
use Nette\Caching\Cache;

class NetteCache implements TableInfoCache
{
	public function __construct(protected Cache $cache)
	{
	}

	public function load(string $className, \Closure $generator): TableInfo
	{
		return $this->cache->load($className, function (&$dependencies) use ($className, $generator) {
			$sourceFile = EntityReflexion::getClassFileName($className);
			if ($sourceFile) {
				$dependencies[Cache::Files] = $sourceFile;
			}

			return $generator();
		});
	}
}
