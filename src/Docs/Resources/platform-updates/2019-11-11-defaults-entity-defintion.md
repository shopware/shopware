[titleEn]: <>(Refactoring of setting defaults in entity definitions)

We changed the behaviour of the `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getDefaults` method.
It is now called only if the entity is newly created.
So a check `$existence->exists()` inside your definition is no longer needed.
If you want to use different defaults for newly created child entities, you could now overwrite `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getChildDefaults`
Due to this refactoring the parameter `EntityExistence $existence` was removed from `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getDefaults`
 
 ### Entity definition before
 ```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class ProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    // ...

    public function getDefaults(EntityExistence $existence): array
    {
        if ($existence->exists()) {
            return [];
        }

        if ($existence->isChild()) {
            return [
                'shippingFree' => true,
            ];
        }
        return [
            'isCloseout' => false,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'shippingFree' => false,
            'restockTime' => 3,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // ...
        ]);
    }
}
```

 ### Entity definition after
 ```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    // ...

    public function getDefaults(): array
    {
        return [
            'isCloseout' => false,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'shippingFree' => false,
            'restockTime' => 3,
        ];
    }

    public function getChildDefaults() : array
    {
        return [
            'shippingFree' => true,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // ...
        ]);
    }
}
```
