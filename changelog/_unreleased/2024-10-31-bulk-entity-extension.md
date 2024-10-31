---
title: Bulk entity extension
issue: 
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
---

# Core
* Added new `BulkEntityExtension` to allow bulk operations on entities.
___
# Upgrade Information
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
        yield 'product' => [
            new FkField('follow_up_id', 'followUp', ProductDefinition::class),
            new ManyToOneAssociationField('followUp', 'follow_up_id', ProductDefinition::class, 'id')
        ];

        yield 'category' => [
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
