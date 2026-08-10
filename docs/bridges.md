# Database bridges

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

## Console command: generating migrations

`Murdej\ActiveRow\Bridges\MakeMigrateCommand` is a ready-made [Symfony
Console](https://symfony.com/doc/current/components/console.html) command wrapping the
[migration generation](migrations.md) tooling — it compares a set of entity classes against the
live database and either prints the resulting SQL or writes it to a timestamped `.sql` file. It
requires `symfony/console` (`composer require symfony/console`), which this package only
suggests, not requires.

Register it in your application's console kernel like any other command, providing:

- the `AbstractDatabase` bridge to compare against,
- the directory generated migration files are written into (`migrationsDir`),
- the entity classes to include — either an explicit list (`entityClasses`), a directory to
  discover them from (`entitiesDir`), or both (they're merged),
- optionally, a `DbTypeDriver` (defaults to `MariaDB`).

```php
use Murdej\ActiveRow\Bridges\MakeMigrateCommand;
use Symfony\Component\Console\Application;

$command = new MakeMigrateCommand(
    database: $database,       // your AbstractDatabase bridge
    migrationsDir: __DIR__ . '/../migrations/structures',
    entitiesDir: __DIR__ . '/../app/Entities',
);

$app = new Application();
$app->add($command);
$app->run();
```

`entitiesDir` is scanned **recursively**, so entities may live in nested subdirectories. Each
file's real namespace and class name are determined by parsing it
(`MakeMigrateCommand::scanEntitiesDir()` is available standalone too) — no PSR-4 naming convention
is assumed. Abstract classes (e.g. a shared base entity) are skipped automatically; interfaces,
traits, and enums are ignored.

Each entity is processed individually; if generation fails for one, the exception message names
the offending entity class (e.g. `Migration generation failed for entity 'App\Entities\Order': ...`)
rather than an opaque error with no indication of which entity was at fault.

Usage from the CLI:

```
# print the diff between the entities and the live database, without writing anything
bin/console migrations:by-diff

# write the diff to migrations/structures/<timestamp>-add-bio.sql
bin/console migrations:by-diff "add bio"
```

Like `DbDeploy::syncTables()` itself, the command never generates destructive SQL — removed
columns, indexes, or foreign keys show up as `-- TODO:` comments in the output, so every generated
migration is safe to review before running.
