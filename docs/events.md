# Events

Entities can hook into the save lifecycle by declaring `@event` annotations and matching methods:

```php
/**
 * @event beforeSave
 * @event afterUpdate onUpdated
 */
class User
{
    use BaseEntity;

    function beforeSave(Event $event): void
    {
        $this->updated = new DateTime();
    }

    function onUpdated(Event $event): void
    {
        // ...
    }
}
```

`@event name` calls a method of the same name; `@event name methodName` calls `methodName`.

Available events, in firing order:

- `beforeSave` — before insert or update
- `beforeInsert` — before insert only
- `prepareDbData` — right before the data array is sent to the database; `$event->data` can be
  read or modified
- `afterInsert` — after insert; `$event->insertId` holds the new primary key
- `beforeUpdate` — before update only; `$event->keys` holds the primary-key condition
- `afterUpdate` — after update
- `afterSave` — after insert or update

Events are **not** fired for a `save()` call on an unmodified existing entity (no query is run at
all in that case).

For app-wide hooks (audit logging, cache invalidation, ...) that should fire for every event on
every entity regardless of per-class `@event` declarations, set a global handler:

```php
use Murdej\ActiveRow\DBEntity;

DBEntity::$globalEventHandler = function (Event $event) {
    // ...
};
```
