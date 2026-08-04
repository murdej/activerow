# Defining an entity

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

## Property types

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

## Size and default value

- `[size]` — size of an integer or string column
- `[size,decimal]` — size and number of decimal places for a `decimal` column
- `[size,decimal,default]` — also sets a default value

Individual parts can be omitted — e.g. `[,,123]` only sets a default value.

## Modificators

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
  [Generating migrations](migrations.md)); the value is used verbatim, without going
  through the usual PHP-type-to-SQL-type conversion, e.g. `(dbType=MEDIUMTEXT)`. The value shares
  the same character set as flags in general (letters, digits, `_`), so it can't contain commas,
  parentheses, or quotes

```
@property int $id (autoIncrement)
@property string $name [200]
@property ?string $note [500,,default note]
@property bool $active (blankNull)
```

## Foreign keys

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

## Serialized columns

A property typed with a plain class and marked `(serialize)` stores the value serialized in a
single column. The class must implement `toDbValue()` (returning the value to store) and either an
instance or static `fromDbValue($value)` (returning a populated instance):

```php
/**
 * @property Money $price (serialize)
 */
```

## Backed enums

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

## Default values

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

## Computed / virtual properties

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
