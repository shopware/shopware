<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Event;

use Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
