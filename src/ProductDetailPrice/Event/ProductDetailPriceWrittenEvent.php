<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductDetailPriceWrittenEvent extends EntityWrittenEvent
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
