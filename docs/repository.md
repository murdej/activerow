# Repository

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
