<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductManufacturer;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductManufacturerDefinition;

class ProductManufacturerWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_manufacturer.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductManufacturerDefinition::class;
    }
}
