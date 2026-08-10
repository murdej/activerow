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

## In a typical app bootstrap

`TableInfo::get()` is called internally by `DBRepository`'s constructor, so wiring up caching is a
one-time bootstrap step — every repository created afterwards benefits automatically, with no
change to how repositories themselves are used:

```php
use Murdej\ActiveRow\TableInfo;
use Murdej\ActiveRow\Caching\FileTableInfoCache;

// once, at bootstrap:
TableInfo::setCache(new FileTableInfoCache(__DIR__ . '/../temp/tableinfo-cache'));

// unchanged from here on:
$users = new UserRepository($database);
$user = $users->get($id);
```

## When to enable it

Parsing an entity's annotations is cheap for a handful of properties, but it does mean a
`ReflectionClass` + doc-comment parse per entity class on every request that touches it. Enabling
`TableInfo::setCache()` is most worth it:

- in production, or any long-lived/many-request environment,
- the more entity classes an application has (e.g. discovered in bulk via
  [`entitiesDir`](bridges.md#console-command-generating-migrations)),
- when the same process/worker handles many requests (caching only saves work *across* requests —
  within a single request `TableInfo::get()` already memoizes in-process, cache or not).

In local development it's usually not worth enabling: annotation changes are picked up
automatically either way (the cache is invalidated by the entity's source file mtime), but it adds
a filesystem/cache round-trip per entity class for no real benefit when each request is a fresh,
short-lived process anyway.

## Clearing or disabling the cache

`TableInfo::setCache(null)` stops consulting any cache and goes back to parsing on every request —
useful for tests, or to temporarily disable caching without touching the underlying storage.

To actively clear stored entries (e.g. after a deploy, if you don't trust mtime-based invalidation
alone): delete the directory's contents for `FileTableInfoCache`, or call `$cache->clean(...)` /
your adapter's own storage-clearing method for `NetteCache`/PSR-16/others — `TableInfoCache` itself
has no `clear()` method, since that's specific to whatever storage it wraps.

## With `MakeMigrateCommand` / `entitiesDir` scanning

[`MakeMigrateCommand`](bridges.md#console-command-generating-migrations) discovers entity classes
by parsing files under `entitiesDir` itself (independent of `TableInfo`/`EntityReflexion`), then
calls `TableInfo::get()` per discovered class to build the schema it diffs against the database.
`TableInfo::setCache()` applies here the same as anywhere else — call it before running the
command:

```php
TableInfo::setCache(new FileTableInfoCache(__DIR__ . '/../temp/tableinfo-cache'));

$command = new MakeMigrateCommand(
    database: $database,
    migrationsDir: __DIR__ . '/../migrations/structures',
    entitiesDir: __DIR__ . '/../app/Entities',
);
```

Whether it's worth it depends on how many entities `entitiesDir` scans and how often the command
runs: for a one-shot, occasional CLI invocation over a small entity set it makes little difference
(the parsing cost is already small and each run is its own fresh process/cache-miss); it pays off
more once there are many entities and the command is run repeatedly (e.g. in a CI pipeline that
also touches other cached code paths, or a long-running worker that also builds migrations).
Either way it's safe to enable — a changed entity is always picked up via the source-file-mtime
invalidation described above, so the generated migration diff never runs against stale schema
metadata.

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

PSR-16 (`Psr\SimpleCache\CacheInterface`) has no built-in dependency/tagging support, so a PSR-16
adapter has to track the source file's mtime itself and compare it on each read:

```php
use Murdej\ActiveRow\EntityReflexion;
use Murdej\ActiveRow\Interfaces\TableInfoCache;
use Murdej\ActiveRow\TableInfo;
use Psr\SimpleCache\CacheInterface;

class Psr16TableInfoCache implements TableInfoCache
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function load(string $className, \Closure $generator): TableInfo
    {
        $key = 'tableinfo.' . str_replace('\\', '.', $className);
        $sourceFile = EntityReflexion::getClassFileName($className);
        $mtime = $sourceFile ? filemtime($sourceFile) : null;

        $entry = $this->cache->get($key);
        if ($entry !== null && $entry['mtime'] === $mtime) {
            return TableInfo::fromArray($entry['data']);
        }

        $ti = $generator();
        $this->cache->set($key, ['mtime' => $mtime, 'data' => $ti->jsonSerialize()]);

        return $ti;
    }
}
```

```php
use Symfony\Component\Cache\Adapter\Psr16Cache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

TableInfo::setCache(new Psr16TableInfoCache(new Psr16Cache(new FilesystemAdapter())));
```

Any PSR-16 implementation works the same way (Symfony Cache, `symfony/cache`'s APCu/Redis
adapters, etc.) — only the constructor call changes.
