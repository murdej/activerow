# Generating migrations

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

Use the `dbType=<value>` [modificator](entities.md#modificators) on a column to override its
generated SQL type entirely (bypassing the driver's normal type conversion) when the built-in
mapping doesn't fit, e.g. `MEDIUMTEXT` instead of the default `TEXT`.

See also: [`MakeMigrateCommand`](bridges.md#console-command-generating-migrations) — a ready-made
Symfony Console command wrapping this for use in an application.
