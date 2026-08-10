<?php

namespace Murdej\ActiveRow\Caching;

use Murdej\ActiveRow\EntityReflexion;
use Murdej\ActiveRow\Interfaces\TableInfoCache;
use Murdej\ActiveRow\TableInfo;

/**
 * Dependency-free TableInfoCache: one JSON file per entity class, invalidated by comparing
 * its mtime against the entity's own source file. Useful when no other cache library
 * (Nette Cache, PSR-16, ...) is already part of the project.
 */
class FileTableInfoCache implements TableInfoCache
{
	public function __construct(protected string $dir)
	{
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			throw new \RuntimeException("Cache directory '$dir' does not exist and could not be created.");
		}
	}

	public function load(string $className, \Closure $generator): TableInfo
	{
		$file = $this->fileFor($className);
		$sourceFile = EntityReflexion::getClassFileName($className);

		if (is_file($file) && (!$sourceFile || filemtime($file) >= filemtime($sourceFile)))
		{
			return TableInfo::fromJson(file_get_contents($file));
		}

		$ti = $generator();
		file_put_contents($file, $ti->toJson(), LOCK_EX);

		return $ti;
	}

	protected function fileFor(string $className): string
	{
		return rtrim($this->dir, '/\\') . '/' . str_replace('\\', '.', $className) . '.json';
	}
}
