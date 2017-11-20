<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductManufacturer;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductManufacturerDefinition;

class ProductManufacturerWrittenEvent extends WrittenEvent
{
    const NAME = 'product_manufacturer.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductManufacturerDefinition::class;
    }
}
