# Querying

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
