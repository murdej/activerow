# Converting to array / JSON

```php
$user->toArray();                          // all mapped columns as [property => value]
$user->toArray(['name', 'email']);         // only the given columns
$user->toArray(null, fkObjects: true);     // include foreign-key columns as their object value

$user->fromArray($data);                                       // mass-assign, `id` excluded
$user->fromArray($data, cols: ['name', 'email']);               // only allow these columns
$user->fromArray($data, ignoreCols: []);                         // don't exclude anything
$user->fromArray($data, autoConvert: true);                      // run values through Converter first

json_encode($user); // works once the entity class implements \JsonSerializable, e.g.:
class User implements \JsonSerializable
{
    use BaseEntity;
}
```

`fromArray()` only ever touches mapped columns present in `$data` (including explicit `null`
values); by default the primary key (`id`) is excluded to protect against mass-assignment.
