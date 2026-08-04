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
the entity is transparently re-fetched from the database, so any DB-side defaults, triggers or
generated column values are reflected immediately.
