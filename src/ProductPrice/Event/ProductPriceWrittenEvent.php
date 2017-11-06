<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductPriceWrittenEvent extends WrittenEvent
{
    const NAME = 'product_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_price';
    }
}
