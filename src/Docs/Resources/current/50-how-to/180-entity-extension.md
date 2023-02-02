[titleEn]: <>(Entity extension)
[metaDescriptionEn]: <>(If you're wondering how to extend existing core entities, this HowTo will have you covered.)
[hash]: <>(article:how_to_entity_extension)

## Overview

If you're wondering how to extend existing core entities, this 'HowTo' will have you covered.
Do not confuse entity extensions with entities' custom fields though, as they serve a different purpose.
In short: Extensions are technical and not configurable by the admin user just like that.
Also they can deal with more complex types than scalar ones.
Custom fields are, by default, configurable by the admin user in the administration and they mostly support scalar types, e.g. a
text-field, a number field or the likes.

## Extending an entity

Own entities can be integrated into the core via the corresponding entry in the `services.xml`.
To extend existing entities, the abstract class `\Shopware\Core\Framework\DataAbstractionLayer\EntityExtension` is used.
The EntityExtension must define which entity should be extended in the `getDefinitionClass` method.
Once this extension is accessed in the system, the extension can add more fields to it:

```php
<?php declare(strict_types=1);

namespace Swag\EntityExtension\Extension\Content\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ObjectField('custom_struct', 'customStruct'))->addFlags(new Runtime())
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
```

This example adds another association named `custom_struct` to the `ProductDefinition`.
The `Runtime` flag tells the data abstraction layer, that you're going to take care of the field's content yourself.
Have a look at our detailed list of [flags](./../60-references-internals/10-core/130-dal.md) and what their purpose is, or find out which [field types](./../60-references-internals/10-core/130-dal.md) are available in Shopware 6.

So, time to take care of the product entities' new field yourself.
You're going to need a new subscriber for this. Have a look [here](./040-register-subscriber.md) to find out how to properly add your own subscriber class.

```php
<?php declare(strict_types=1);

namespace Swag\EntityExtension\Subscriber;

use Swag\EntityExtension\Struct\MyCustomStruct;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Product\ProductEvents;

class MySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'onProductsLoaded'
        ];
    }

    public function onProductsLoaded(EntityLoadedEvent $event): void
    {
        /** @var ProductEntity $productEntity */
        foreach ($event->getEntities() as $productEntity) {
            $productEntity->addExtension('custom_struct', new MyCustomStruct());
        }
    }
}
```

As you can see, the subscriber listens to the `PRODUCT_LOADED` event, which is triggered every time a set of products
is requested.
The listener `onProductsLoaded` then adds a custom struct into the new field.

Content of the respective `services.xml`:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\EntityExtension\Extension\Content\Product\CustomExtension">
            <tag name="shopware.entity.extension"/>
        </service>

        <service id="Swag\EntityExtension\Subscriber\MySubscriber">
            <tag name="kernel.event_subscriber" />
        </service>
    </services>
</container>
```

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-entity-extension).
