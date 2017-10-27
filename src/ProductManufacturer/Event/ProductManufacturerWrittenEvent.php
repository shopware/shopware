<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductManufacturerWrittenEvent extends WrittenEvent
{
    const NAME = 'product_manufacturer.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_manufacturer';
    }
}
