<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductManufacturerTranslation;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductManufacturerTranslationDefinition;

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
