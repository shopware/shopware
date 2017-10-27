<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductDetailPriceWrittenEvent extends WrittenEvent
{
    const NAME = 'product_detail_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_detail_price';
    }
}
