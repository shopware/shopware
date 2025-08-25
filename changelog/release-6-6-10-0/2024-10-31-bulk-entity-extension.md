---
title: Bulk entity extension
issue: NEXT-39734
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
---

# Core
* Added new `BulkEntityExtension` to allow bulk operations on entities.
* Deprecated `EntityExtension::getDefinitionClass`, function will be removed, implement `EntityExtension::getEntityName()`

___
# Upgrade Information
## Deprecated EntityExtension::getDefinitionClass
Since (app) custom entities and entities defined via PHP attributes do not have a definition class, the method `EntityExtension::getDefinitionClass` has been deprecated. 
It will be replaced by `EntityExtension::getEntityName`, which needs to return the entity name. This can already be implemented now.

Before:

```php
<?php

namespace Examples\Extension;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;

class MyEntityExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    { 
        return ProductDefinition::class;
    }
}
```

After:

```php
<?php

namespace Examples\Extension;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;

class MyEntityExtension extends EntityExtension
{
    public function getEntityName() : string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
```

## Feature: Bulk entity extension
The new `BulkEntityExtension` allows to define fields for different entities within one class. It removes the overhead of creating multiple classes for each entity and allows to define the fields in one place.

```php
<?php

namespace Examples\Extension;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class MyEntityExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield ProductDefinition::ENTITY_NAME => [
            new FkField('follow_up_id', 'followUp', ProductDefinition::class),
            new ManyToOneAssociationField('followUp', 'follow_up_id', ProductDefinition::class, 'id')
        ];

        yield CategoryDefinition::ENTITY_NAME => [
            new FkField('linked_category_id', 'linkedCategoryId', CategoryDefinition::class),
            new ManyToOneAssociationField('category', 'linked_category_id', CategoryDefinition::class, 'id')
        ];
    }
}
```

```xml
<service id="Examples\Extension\MyEntityExtension">
    <tag name="shopware.bulk.entity.extension"/>
</service>
```
___
# Next Major Version Changes

## Removed EntityExtension::getDefinitionClass 
The method `EntityExtension::getDefinitionClass` has been removed. It is replaced by `EntityExtension::getEntityName`, which needs to return the entity name.

Before:

```php
<?php

namespace Examples\Extension;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;

class MyEntityExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    { 
        return ProductDefinition::class;
    }
}
```

After:

```php
<?php

namespace Examples\Extension;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;

class MyEntityExtension extends EntityExtension
{
    public function getEntityName() : string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
```
