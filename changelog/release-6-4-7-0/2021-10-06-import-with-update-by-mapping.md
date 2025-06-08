---
title: Import with update by mapping
issue: NEXT-16704
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `update_by` json field to `ImportExportProfileDefinition` to include a mapping from each entity included in the import to a dedicated field which will be used to resolve the primary key from
* Added `UpdateByCollection` of `UpdateBy` instances to contain `update_by` mapping
* Changed `Shopware\Core\Content\ImportExport\Struct\Config` to receive `update_by` mapping for creating and serving `UpdateByCollection`
* Added `PrimaryKeyResolver` that will resolve primary keys within records by mappings of `UpdateByCollection` if provided
* Changed `EntityPipe` and `ToOneSerializer` to call `PrimaryKeyResolver::resolvePrimaryKeyFromUpdatedBy` before deserialization
___
# Upgrade Information

Added a new constructor argument `iterable $updateBy = []` in `Shopware\Core\Content\ImportExport\Struct\Config` which will become required starting from `v6.5.0`.

The new parameter is used to pass a mapping from an entity to a single field of the corresponding definition. This mapping is then used to resolve the primary key of a data set. This provides an alternative to using IDs for updating existing data sets.

### Before

```php
$config = new Config(
    [['key' => 'productNumber', 'mappedKey' => 'product_number']], 
    ['sourceEntity' => $sourceEntity]
);
```

### After

```php
$config = new Config(
    [['key' => 'productNumber', 'mappedKey' => 'product_number']], 
    ['sourceEntity' => $sourceEntity],
    [['entityName' => ProductDefinition::ENTITY_NAME, 'mappedKey' => 'productNumber']]
);
```
