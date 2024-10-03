# Murdej activeRow

## Entity

Property is defined in phpdoc `@property type $name [size](modificators)` before class definition.

### List of possible primitive types:

- `int`
- `decimal`
- `double`
- `float`
- `array`
- `string`
- `bool`
- `DateTime`

It is also possible to define a class as a property. It can then be stored serialized or as a foreign key on another entity in the db.

### Size:

- `[size]` - define size of integer or string
- `[size,decimal]` - define size and dec form decimal
- `[size,decimal,default]` - define also default value

Individual parts can be omitted, for example the `[,,123]` entry defines only the default value.

### Modificators:

- `primary` - column is primmary key
- `autoIncrement` - column is primmary key with autoincrement
- `unique` - column has unique index
- `indexed` - column has index
- `nullable` - column is nullable (Is it possible to define and ? before the type declaration.)
- `forInsert` - To be used when insert 
- `forUpdate` - To be used when update
- `serialize` - Allows to save a complex object serialized to db and then retrieve it from db.
- `blankNull` - Convert empty values to null 
- `json` - Save array a JSON
- `fk` - Reference to another entity using foreign key

### Events

- `beforeSave` - call before insert or update  
- `beforeInsert` - call before insert   
- `afterInsert` - call after insert  
- `beforeUpdate` - call before update  
- `afterUpdate` - call after update  
- `afterSave` - call after insert or update

callback method fignature:
```php
function eventName(Event $event): void
```
