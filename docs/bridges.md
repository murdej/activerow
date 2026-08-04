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
