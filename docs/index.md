# Murdej ActiveRow

ActiveRow is a lightweight, annotation-driven Active Record layer for PHP 8.2+. Entities are
plain PHP classes annotated with `@property` doc-comments; the library reads those annotations via
reflection, builds SQL through [`murdej/query-maker-php`](https://github.com/murdej/query-maker-php),
and talks to the actual database through a small, swappable `AbstractDatabase` bridge — so the
library itself has no hard dependency on any specific database library.

- [Installation](#installation)
- [Quick example](#quick-example)
- [Defining an entity](entities.md)
  - [Property types](entities.md#property-types)
  - [Size and default value](entities.md#size-and-default-value)
  - [Modificators](entities.md#modificators)
  - [Foreign keys](entities.md#foreign-keys)
  - [Serialized columns](entities.md#serialized-columns)
  - [Backed enums](entities.md#backed-enums)
  - [Default values](entities.md#default-values)
  - [Computed / virtual properties](entities.md#computed--virtual-properties)
- [Repository](repository.md)
- [Querying](querying.md)
- [Saving entities](saving.md)
- [Events](events.md)
- [Converting to array / JSON](converting.md)
- [Database bridges](bridges.md)
  - [Console command: generating migrations](bridges.md#console-command-generating-migrations)
- [Generating migrations](migrations.md)
- [Caching entity metadata](caching.md)
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
[Database bridges](bridges.md).

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

See [Defining an entity](entities.md), [Repository](repository.md), [Querying](querying.md),
[Saving entities](saving.md), [Events](events.md), [Converting to array / JSON](converting.md),
[Database bridges](bridges.md), [Generating migrations](migrations.md) and [Caching entity
metadata](caching.md) for the full reference.

## Known limitations

- Related (has-many / belongs-to-many) entity collections are not implemented yet — only
  single-value [foreign keys](entities.md#foreign-keys) are supported for now.
- Many-to-many junction-table helpers and arbitrary raw-table access have no built-in helper;
  query the junction table through your own `AbstractDatabase`/`DBSelect` usage.
