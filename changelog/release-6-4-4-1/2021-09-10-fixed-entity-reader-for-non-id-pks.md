---
title: Fixed EntitySearcher for PrimaryKeys other than `id`
issue: NEXT-17105
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper` to fix problem when entities have primary keys, where the storage name and the property name differs.
* Deprecated reading entities with the storage name of the primary key, use the property name instead. 
___
# Upgrade Information

## Deprecating reading entities with the storage name of the primary key fields

When you added a custom entity definition with a combined primary key you need to pass the field names when you want to read specific entities.
The use of storage names when reading entities is deprecated by now, please use the property names instead.
The support of reading entities with the storage name of the primary keys will be removed in 6.5.0.0.

### Before
```php
new Criteria([
    [
        'storage_name_of_first_pk' => 1,
        'storage_name_of_second_pk' => 2,
    ],
]);
```

### Now
```php
new Criteria([
    [
        'propertyNameOfFirstPk' => 1,
        'propertyNameOfSecondPk' => 2,
    ],
]);
```
