<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductManufacturerTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductManufacturerTranslationDefinition;

class ProductManufacturerTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_manufacturer_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductManufacturerTranslationDefinition::class;
    }
}
