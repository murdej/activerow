# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- `dbType=<value>` column flag: overrides the SQL column type used when generating migrations,
  used verbatim without going through the usual PHP-to-SQL type conversion (e.g.
  `(dbType=MEDIUMTEXT)`).
- Migration generation tooling ported from `nette-activerow`, under `Murdej\ActiveRow\Migrations`:
  - `DbDeploy` — compares an entity's declared schema against the live database schema and
    generates `CREATE TABLE` / `ALTER TABLE` SQL. Destructive changes (removed columns, indexes,
    foreign keys) are only ever emitted as `-- TODO:` comments, never `DROP` statements.
  - `DbSchemaReader` — reads the live database schema through the existing `AbstractDatabase`
    bridge abstraction (`dbExecuteQuery()`), instead of a hard dependency on Nette Database as in
    the original.
  - `DbTypeDriver` interface (`Murdej\ActiveRow\Interfaces`) — extracts database-engine-specific
    SQL generation and schema introspection behind a driver; only a MariaDB driver
    (`Murdej\ActiveRow\Migrations\MariaDB`) is provided for now.
- `ColumnInfo::$fkTable` and `ColumnInfo::$liveType` properties, used by the new schema reader to
  represent a column's live/introspected state.
- `DBEntity::$globalEventHandler` static hook, called for every entity event in addition to the
  entity's own event method.
- `Event::prepareDbData` event, fired before the data array for an insert/update is built.
- `BaseEntity::__init()` lifecycle hook, called right after entity creation.
- `BaseEntity`/`DBEntity::toArray()`.
- `BaseEntity::fromArray()` gained `$cols`, `$ignoreCols`, and `$autoConvert` parameters, and now
  accepts any `array|\ArrayAccess`.
- `BaseEntity` now implements `\JsonSerializable`.
- `DBSelect` gained `fetchPairs()`, `fetchField()`, `fetchArray()`, `fetchArrayWithExtraFields()`,
  and `\Countable` support (`count()`).
- Support for `\BackedEnum` columns in `Converter` (both directions), and automatic string ->
  `DateTime` conversion.
- Support for a static `fromDbValue()` on serialized column types, alongside the existing instance
  method.
- `get` / `set` / `getset` column flags: declare a mapped, typed property that is backed by
  `get{Name}()`/`is{Name}()`/`set{Name}()` methods instead of raw storage and is never persisted —
  excluded from reads/writes against the database and from generated migrations.
  `TableInfo::$dbColumns` now holds the subset of `$columns` actually backed by the database.
- `Murdej\ActiveRow\Bridges\MakeMigrateCommand`: a Symfony Console command wrapping the migration
  tooling, comparing a set of entities against the live database and printing or saving the
  resulting diff SQL. Entity classes can be listed explicitly (`entityClasses`), discovered by
  recursively scanning a directory (`entitiesDir`), or both (merged) — directory scanning
  determines each file's real namespace/class name by parsing it (no PSR-4 convention assumed) and
  skips abstract classes automatically. Entities are processed one at a time, so a generation
  failure's exception message names the specific entity class that caused it.
- `Murdej\ActiveRow\Migrations\MariaDB::getSqlDataType()` maps `\BackedEnum` column types to
  `INT`/`VARCHAR` (based on the enum's backing type and, for string-backed enums, the longest
  case value), so backed enums can be used as column types without a `dbType=` override.
- `Murdej\ActiveRow\EntityReflexion`: entity annotation parsing extracted out of `TableInfo`/
  `ColumnInfo` into its own class. It now also reads the entity file's `use` imports (via
  `token_get_all()`) and resolves any bare, non-primitive column type (`fk`, `serialize`,
  `\BackedEnum`) against them before falling back to the entity's own namespace — so an
  `fk`/`serialize`/enum type imported from another namespace no longer has to be written fully
  qualified in the annotation.
- `TableInfo`/`ColumnInfo` now implement `\JsonSerializable`, and gained `fromArray()`/
  `fromJson()`; `TableInfo` also gained `toJson()`. Parsed entity metadata can be serialized and
  restored without re-running reflection/annotation parsing.
- `TableInfo::addColumn()`, extracted from the column-registration logic in `parseClass()`/
  `EntityReflexion::parseTable()`, also used by `TableInfo::fromArray()`.

### Changed

- Added strict PHP 8.2 type hints throughout `src/`, including the vendored NReflection helpers.
- `DBRepository` is now directly instantiable (`className` is passed to the constructor) instead
  of requiring a subclass per entity.
- `AbstractDatabase::getEntityByPrimary()` now builds its query from `columnName` correctly and
  gained transaction hooks (`dbBeginTransaction()`, `dbCommit()`, `dbRollback()`).
- After `save()`, `DBEntity` re-fetches the row from the database to refresh its stored state.

### Fixed

- Auto-increment columns generated by the migration tooling no longer emit a duplicate
  `PRIMARY KEY` constraint (`AUTO_INCREMENT PRIMARY KEY PRIMARY KEY`), which was invalid SQL.
- `Bridges\NetteDatabase` was missing `dbBeginTransaction()`/`dbCommit()`/`dbRollback()`, making it
  impossible to instantiate (`AbstractDatabase` declares them abstract).
- `ColumnInfo`'s "Invalid column flag"/"Invalid column def" exceptions, and `TableInfo`'s "Must
  define any property"/"Invalid table def" exceptions, now actually name the offending
  entity/property — they referenced a `$this->propertyInfo` magic property that doesn't exist (no
  `__get()` is defined), so the class/property name was silently dropped from the message.
- `MariaDB::getSqlDataType()`'s backed-enum detection could let a raw `ReflectionException` escape
  (e.g. `Class "..." does not exist`) instead of the library's own "Unknown type" exception, when
  `enum_exists()` and `ReflectionEnum` disagreed about a type being loadable (seen with a class
  reachable only through a stale/optimized classmap, not through PSR-4). The fallback exception now
  also names the offending entity **and** property, and hints when the type looks like an
  unresolvable class/enum.
