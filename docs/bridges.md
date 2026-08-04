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
- the list of fully-qualified entity class names to include (or build it with the
  `MakeMigrateCommand::scanEntitiesDir()` helper, which lists every `*.php` file in a directory as
  a class in the given namespace),
- the directory generated migration files are written into,
- optionally, a `DbTypeDriver` (defaults to `MariaDB`).

```php
use Murdej\ActiveRow\Bridges\MakeMigrateCommand;
use Symfony\Component\Console\Application;

$entityClasses = MakeMigrateCommand::scanEntitiesDir(
    namespace: 'App\\Entities',
    dir: __DIR__ . '/../app/Entities',
    exclude: ['BaseEntity'],
);

$command = new MakeMigrateCommand(
    database: $database,       // your AbstractDatabase bridge
    entityClasses: $entityClasses,
    migrationsDir: __DIR__ . '/../migrations/structures',
);

$app = new Application();
$app->add($command);
$app->run();
```

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
