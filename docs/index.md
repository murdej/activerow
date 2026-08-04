# Murdej ActiveRow

ActiveRow is a lightweight, annotation-driven Active Record layer for PHP 8.2+. Entities are
plain PHP classes annotated with `@property` doc-comments; the library reads those annotations via
reflection, builds SQL through [`murdej/query-maker-php`](https://github.com/murdej/query-maker-php),
and talks to the actual database through a small, swappable `AbstractDatabase` bridge — so the
library itself has no hard dependency on any specific database library.

- [Installation](#installation)
- [Quick example](#quick-example)
- [Defining an entity](#defining-an-entity)
  - [Property types](#property-types)
  - [Size and default value](#size-and-default-value)
  - [Modificators](#modificators)
  - [Foreign keys](#foreign-keys)
  - [Serialized columns](#serialized-columns)
  - [Backed enums](#backed-enums)
  - [Default values](#default-values)
  - [Computed / virtual properties](#computed--virtual-properties)
- [Repository](#repository)
- [Querying](#querying)
- [Saving entities](#saving-entities)
- [Events](#events)
- [Converting to array / JSON](#converting-to-array--json)
- [Database bridges](#database-bridges)
- [Generating migrations](#generating-migrations)
- [Known limitations](#known-limitations)

## Installation

```
composer require murdej/activerow
```

The library needs a database bridge to actually run queries. A ready-made bridge for
[Nette Database](https://doc.nette.org/en/database) is included
(`Murdej\ActiveRow\Bridges\NetteDatabase`); install it with:

```
composer require nette/database
```

If you use a different database layer (CodeIgniter, PDO, ...), implement your own bridge — see
[Database bridges](#database-bridges).

## Quick example

```php
use Murdej\ActiveRow\Traits\BaseEntity;
use Murdej\ActiveRow\DBRepository;
use Murdej\ActiveRow\Bridges\NetteDatabase;

/**
 * @dbTable
 * @property int $id (autoIncrement)
 * @property string $name [200]
 * @property string $email [200]
 */
class User
{
    use BaseEntity;
}

/**
 * @extends DBRepository<User>
 */
class UserRepository extends DBRepository
{
    protected ?string $className = User::class;
}

$database = new NetteDatabase($explorer); // $explorer is a Nette\Database\Explorer
$users = new UserRepository($database);

$user = $users->newEntity();
$user->name = 'Franta';
$user->email = 'franta@example.com';
$user->save();

$user = $users->get($id);
$user->name = 'Franta Novák';
$user->save();

foreach ($users->findBy(['name' => 'Franta Novák']) as $user) {
    echo $user->email . "\n";
}
```

## Defining an entity

A class becomes an entity by using the `Murdej\ActiveRow\Traits\BaseEntity` trait and adding the
`@dbTable` class annotation. Each mapped property is declared as a `@property` doc-comment on the
class:

```
@property type $name [size](modificators)
```

- `@dbTable` — marks the class as an entity. With no value the table name is derived from the
  class name (`User` → `user`); pass an explicit name to override it, e.g. `@dbTable app_users`.
- `@property` — one line per column. `type`, `[size]` and `(modificators)` are all optional except
  the type and the `$name`.

### Property types

- `int`
- `decimal`
- `double`
- `float`
- `array`
- `string`
- `bool`
- `DateTime`
- `\BackedEnum`-implementing enums — see [Backed enums](#backed-enums)
- any other class name — either a [serialized value object](#serialized-columns) or a
  [foreign-key reference](#foreign-keys), depending on modificators

Prefixing the type with `?` marks the column nullable, e.g. `@property ?string $note`.

### Size and default value

- `[size]` — size of an integer or string column
- `[size,decimal]` — size and number of decimal places for a `decimal` column
- `[size,decimal,default]` — also sets a default value

Individual parts can be omitted — e.g. `[,,123]` only sets a default value.

### Modificators

Comma-separated flags in parentheses after the type/size:

- `primary` (alias `pk`) — column is the primary key
- `autoIncrement` — column is an auto-increment primary key (implies `primary`, not writable on
  insert)
- `unique` — column has a unique index
- `indexed` (alias `index`) — column has an index
- `nullable` (alias `?`) — column is nullable — equivalent to prefixing the type with `?`
- `forInsert` / `forUpdate` — restrict when the column is written; prefix with `!` to disable
  (e.g. `!forUpdate` for a column that is only ever set on insert)
- `serialize` — store a complex object serialized into the column, see
  [Serialized columns](#serialized-columns)
- `blankNull` — convert empty (falsy) values to `null` before saving
- `json` — store an array as JSON
- `fk` — foreign key referencing another entity class, see [Foreign keys](#foreign-keys)
- `dbType=<value>` — overrides the SQL column type used when generating migrations (see
  [Generating migrations](#generating-migrations)); the value is used verbatim, without going
  through the usual PHP-type-to-SQL-type conversion, e.g. `(dbType=MEDIUMTEXT)`. The value shares
  the same character set as flags in general (letters, digits, `_`), so it can't contain commas,
  parentheses, or quotes

```
@property int $id (autoIncrement)
@property string $name [200]
@property ?string $note [500,,default note]
@property bool $active (blankNull)
```

### Foreign keys

A property typed with another entity class and marked `(fk)` is stored as an integer column
(named `{property}Id` by default) and transparently resolves to the related entity on read:

```php
/**
 * @dbTable
 * @property int $id (autoIncrement)
 * @property string $name [200]
 */
class Company
{
    use BaseEntity;
}

/**
 * @dbTable
 * @property int $id (autoIncrement)
 * @property string $name [200]
 * @property Company $company (fk)
 */
class Employee
{
    use BaseEntity;
}

$employee->company;          // lazily fetches and returns a Company entity
$employee->companyId;        // the raw foreign key value
$employee->companyId = 5;    // set the reference by id
$employee->company = $x;     // throws — set the id column instead
```

### Serialized columns

A property typed with a plain class and marked `(serialize)` stores the value serialized in a
single column. The class must implement `toDbValue()` (returning the value to store) and either an
instance or static `fromDbValue($value)` (returning a populated instance):

```php
/**
 * @property Money $price (serialize)
 */
```

### Backed enums

Properties typed with a PHP `BackedEnum` are converted automatically — no `serialize` modificator
needed:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

/**
 * @property Status $status
 */
```

### Default values

There are two independent ways to give a column a default value:

1. **`[size,decimal,default]`** or a class-level **`@defaultValues`** annotation with a static
   `defaultValues(): array` method (keyed by property name). These act as a *fallback*: they're
   used when reading a property that was never set on the entity, and — for new rows — they are
   also included in the generated `INSERT` even if the property was never explicitly touched.
   ```php
   /**
    * @defaultValues
    * @property string $status [,,active]
    */
   class Order
   {
       use BaseEntity;

       static function defaultValues(): array
       {
           return ['currency' => 'CZK'];
       }
   }
   ```
2. **`dbDefaultValues(): array`** (static or instance method, no annotation needed). Called once
   right after a new entity is created via `$repository->newEntity()`, and applied through
   `fromArray()` — the values become normal, explicitly-set values from that point on (not a
   lazy fallback).
   ```php
   class User
   {
       use BaseEntity;

       static function dbDefaultValues(): array
       {
           return ['foo' => 42];
       }
   }
   ```

### Computed / virtual properties

Reading or writing a name that isn't a mapped column falls back to calling `get{Name}()` /
`is{Name}()` (read) or `set{Name}()` (write) on the entity, so you can expose computed properties
alongside real columns:

```php
class User
{
    use BaseEntity;

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}

echo $user->fullName; // calls getFullName()
```

## Repository

Each entity gets a repository class extending `Murdej\ActiveRow\DBRepository`, pointing at the
entity class:

```php
/**
 * @extends DBRepository<User>
 */
class UserRepository extends DBRepository
{
    protected ?string $className = User::class;
}

$users = new UserRepository($database);
```

Repository methods:

- `get($id): ?User` — fetch by primary key, `null` if `$id === null` or nothing is found
- `getBy(array $conditions): ?User` — fetch the first row matching conditions
- `findBy(array $conditions): DBSelect` — build a filtered `DBSelect`
- `findAll(): DBSelect` — all rows
- `newEntity(array $initData = []): User` — create a new, unsaved entity (applies
  `dbDefaultValues()` if defined, then `$initData`)
- `newSelect(): DBSelect` — a fresh, unfiltered `DBSelect` to build a query on

## Querying

`DBSelect` (returned by `findBy()`/`findAll()`/`newSelect()`) is an `Iterator` and `Countable` of
entities and supports a small fluent query builder on top of `murdej/query-maker-php`:

```php
$select = $users
    ->findBy(['status' => 'active', '!role' => 'admin'])
    ->order('name ASC')
    ->limit(20, 0);

foreach ($select as $user) { /* ... */ }

count($select);              // real SQL COUNT(*), ignores limit/offset
```

- `where($conditions)` — merge in more conditions (array of `column => value`, `!column => value`
  for negation, `column => [values]` for `IN`, or a raw condition string)
- `select(...$columns)` — restrict/override selected columns
- `order(...$columns)` — e.g. `order('name ASC', 'id DESC')`
- `limit(int $limit, int $offset = 0)`

Fetching:

- `fetchEntity(): ?object` / `fetchRow(): ?array` — advance and return the current entity or its
  raw row
- `fetchEntities(): array` — all rows as entities
- `fetchRows(): array` — all rows as raw associative arrays
- `fetchArray(bool $rowIsArray = false): array` — all rows as entities, or as `toArray()` arrays
  when `$rowIsArray` is true
- `fetchArrayWithExtraFields(): array` — raw DB row merged with the entity's computed
  `toArray()` fields
- `fetchPairs($key, $value = null): array` — build a `key => value` map; `$key`/`$value` may be a
  column name or a `callable(object): mixed`
- `fetchField($field = null)` — a single scalar value (first column of the first row, or the
  named field)

## Saving entities

```php
$user = $users->newEntity();
$user->name = 'Franta';
$user->save(); // INSERT — returns true

$user->name = 'Franta Novák';
$user->save(); // UPDATE — returns true

$user->save(); // nothing changed — returns false, no query, no events fired
```

Only properties that were actually assigned (or, on insert, have a default value — see
[Default values](#default-values)) are sent to the database. After a successful insert the entity
is transparently re-fetched from the database, so any DB-side defaults, triggers or generated
column values are reflected immediately.

## Events

Entities can hook into the save lifecycle by declaring `@event` annotations and matching methods:

```php
/**
 * @event beforeSave
 * @event afterUpdate onUpdated
 */
class User
{
    use BaseEntity;

    function beforeSave(Event $event): void
    {
        $this->updated = new DateTime();
    }

    function onUpdated(Event $event): void
    {
        // ...
    }
}
```

`@event name` calls a method of the same name; `@event name methodName` calls `methodName`.

Available events, in firing order:

- `beforeSave` — before insert or update
- `beforeInsert` — before insert only
- `prepareDbData` — right before the data array is sent to the database; `$event->data` can be
  read or modified
- `afterInsert` — after insert; `$event->insertId` holds the new primary key
- `beforeUpdate` — before update only; `$event->keys` holds the primary-key condition
- `afterUpdate` — after update
- `afterSave` — after insert or update

Events are **not** fired for a `save()` call on an unmodified existing entity (no query is run at
all in that case).

For app-wide hooks (audit logging, cache invalidation, ...) that should fire for every event on
every entity regardless of per-class `@event` declarations, set a global handler:

```php
use Murdej\ActiveRow\DBEntity;

DBEntity::$globalEventHandler = function (Event $event) {
    // ...
};
```

## Converting to array / JSON

```php
$user->toArray();                          // all mapped columns as [property => value]
$user->toArray(['name', 'email']);         // only the given columns
$user->toArray(null, fkObjects: true);     // include foreign-key columns as their object value

$user->fromArray($data);                                       // mass-assign, `id` excluded
$user->fromArray($data, cols: ['name', 'email']);               // only allow these columns
$user->fromArray($data, ignoreCols: []);                         // don't exclude anything
$user->fromArray($data, autoConvert: true);                      // run values through Converter first

json_encode($user); // works once the entity class implements \JsonSerializable, e.g.:
class User implements \JsonSerializable
{
    use BaseEntity;
}
```

`fromArray()` only ever touches mapped columns present in `$data` (including explicit `null`
values); by default the primary key (`id`) is excluded to protect against mass-assignment.

## Database bridges

The library itself never talks to a database directly — every entity operation goes through an
`AbstractDatabase` implementation. A bridge only has to implement three methods:

```php
abstract class AbstractDatabase
{
    // Run a query with positional `?` placeholders, return rows as plain associative arrays.
    abstract function dbExecuteQuery(string $query, array $params): array;

    // Insert a row, return the new primary key.
    abstract function insertRow(string $tableName, array $data);

    // Update rows matching $keys (column => value).
    abstract function updateRow(string $tableName, array $data, array $keys);
}
```

`Murdej\ActiveRow\Bridges\NetteDatabase` is a ready-to-use bridge around
`Nette\Database\Explorer`:

```php
use Murdej\ActiveRow\Bridges\NetteDatabase;

$database = new NetteDatabase($explorer);
```

To integrate a different database layer, extend `AbstractDatabase` the same way — see
`src/Bridges/CodeIgniterDatabase.php` for a stub to fill in.

## Generating migrations

`Murdej\ActiveRow\Migrations\DbDeploy` compares an entity's declared schema (`TableInfo`/
`ColumnInfo`, built from `@property` annotations) against the live database schema and generates
`CREATE TABLE` / `ALTER TABLE` SQL. Schema-specific SQL syntax (identifier quoting, type mapping,
`DESCRIBE`/`information_schema` introspection queries) lives in a `DbTypeDriver` implementation —
currently only `Murdej\ActiveRow\Migrations\MariaDB` is provided.

```php
use Murdej\ActiveRow\Migrations\{DbDeploy, DbSchemaReader, MariaDB};
use Murdej\ActiveRow\TableInfo;

$driver = new MariaDB();
$reader = new DbSchemaReader($database, $driver); // $database is an AbstractDatabase bridge
$deploy = new DbDeploy($driver);

// Fresh install — CREATE TABLE for every entity:
echo $deploy->createTables([TableInfo::get(User::class), TableInfo::get(Order::class)]);

// Existing database — compare and generate ALTER/CREATE statements:
$pairs = [];
foreach ([User::class, Order::class] as $class) {
    $ti = TableInfo::get($class);
    $pairs[] = [$ti, $reader->getTableInfo($ti->tableName)];
}
echo $deploy->syncTables($pairs);
```

`syncTables()` only ever generates additive changes (`ADD COLUMN`, `MODIFY COLUMN`, `ADD INDEX`/
`ADD FOREIGN KEY`); removed columns, indexes, or foreign keys are emitted as `-- TODO:` comments
instead of destructive `DROP` statements, so generated SQL is always safe to review and run.

Use the `dbType=<value>` [modificator](#modificators) on a column to override its generated SQL
type entirely (bypassing the driver's normal type conversion) when the built-in mapping doesn't
fit, e.g. `MEDIUMTEXT` instead of the default `TEXT`.

## Known limitations

- Related (has-many / belongs-to-many) entity collections are not implemented yet — only
  single-value [foreign keys](#foreign-keys) are supported for now.
- Many-to-many junction-table helpers and arbitrary raw-table access have no built-in helper;
  query the junction table through your own `AbstractDatabase`/`DBSelect` usage.
