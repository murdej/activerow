# Caching entity metadata

`TableInfo::get($className)` parses an entity's `@property` doc-comments via reflection the first
time it's asked for. The result is memoized in a static in-process array, so within a single
request it only happens once per class — but that memoization is reset on every new
request/process, so the parsing runs again each time.

`TableInfo::setCache()` plugs a persistent cache in front of that parsing step, so the annotation
parsing only has to happen once until the entity's source file actually changes:

```php
use Murdej\ActiveRow\TableInfo;
use Murdej\ActiveRow\Caching\FileTableInfoCache;

TableInfo::setCache(new FileTableInfoCache(__DIR__ . '/../temp/cache'));
```

Call it once, early in your bootstrap, before any `TableInfo::get()` / entity usage. Pass `null` to
go back to parsing on every request.

## Built-in implementations

- **`Murdej\ActiveRow\Caching\FileTableInfoCache`** — dependency-free. Stores one JSON file per
  entity class (via `TableInfo::toJson()`/`fromJson()`) in a directory you provide.
  ```php
  new FileTableInfoCache(__DIR__ . '/../temp/tableinfo-cache')
  ```
- **`Murdej\ActiveRow\Bridges\NetteCache`** — wraps a [Nette
  Cache](https://doc.nette.org/en/caching) instance; requires `nette/caching`
  (`composer require nette/caching`).
  ```php
  use Murdej\ActiveRow\Bridges\NetteCache;
  use Nette\Caching\Cache;
  use Nette\Caching\Storages\FileStorage;

  $cache = new Cache(new FileStorage(__DIR__ . '/../temp/cache'));
  TableInfo::setCache(new NetteCache($cache));
  ```
  Use this if your application already has a Nette Cache storage configured (e.g. from a DI
  container) — it shares that storage instead of writing to its own directory.

Both invalidate automatically: each cached entry is tied to the entity's own source file, so
editing an entity's `@property` annotations invalidates its cache entry the next time it's
requested, with no manual cache-clearing step.

## Writing your own adapter

To use a different cache (PSR-16, Redis, APCu, ...), implement
`Murdej\ActiveRow\Interfaces\TableInfoCache`:

```php
interface TableInfoCache
{
    public function load(string $className, \Closure $generator): TableInfo;
}
```

`$generator` returns a freshly parsed `TableInfo` when called; your implementation should call it
and store the result on a cache miss, and return the stored value on a hit. Use
`Murdej\ActiveRow\EntityReflexion::getClassFileName($className)` to get the entity's source file
path for invalidation (e.g. a file mtime check, or a `Cache::Files` dependency as `NetteCache`
does) — see `src/Caching/FileTableInfoCache.php` and `src/Bridges/NetteCache.php` for reference
implementations.
