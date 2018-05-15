<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductManufacturerTranslation;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductManufacturerTranslationDefinition;

class ProductManufacturerTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_manufacturer_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductManufacturerTranslationDefinition::class;
    }
}
