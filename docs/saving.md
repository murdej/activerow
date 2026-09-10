# Saving entities

```php
$user = $users->newEntity();
$user->name = 'Franta';
$user->save(); // INSERT — returns true

$user->name = 'Franta Novák';
$user->save(); // UPDATE — returns true

$user->save(); // nothing changed — returns false, no query, no events fired
```

Only properties that were actually assigned (or, on insert, have a default value — see
[Default values](entities.md#default-values)) are sent to the database. After a successful insert
or update the entity is transparently re-fetched from the database, so any DB-side defaults,
triggers or generated column values — as well as the values you just wrote — are reflected
immediately when read back from the same instance.

## Transactions

`$database->inTransaction(callable $operations)` (available on any `AbstractDatabase` bridge) runs
`$operations` inside a database transaction, committing on success and rolling back (then
rethrowing) if it throws:

```php
$database->inTransaction(function () use ($from, $to, $amount) {
    $from->balance -= $amount;
    $from->save();
    $to->balance += $amount;
    $to->save();
});
```
