[titleEn]: <>(Entity extension)
[titleDe]: <>(Entity extension)
[wikiUrl]: <>(../plugin-system/entity-extension?category=shopware-platform-en/plugin-system)

Own entities can be integrated into the core via the corresponding entry in the `services.xml`.
To extend existing entities, the `\Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface` is used.
The EntityExtension must define which entity should be extended. 
Once this entity is accessed in the system, the extension can add more fields to it:
```php
<?php declare(strict_types=1);

namespace GettingStarted\Content\Product;

use GettingStarted\Content\Promotion\PromotionDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PromotionExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('promotion', PromotionDefinition::class, 'product_id'))->addFlags(new Extension())
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
```
Content of your `services.xml`
```xml
<service id="GettingStarted\Content\Product\PromotionExtension">
    <tag name="shopware.entity.extension"/>
</service>
```

This example adds another association named `promotion` to the `ProductDefinition` class.
