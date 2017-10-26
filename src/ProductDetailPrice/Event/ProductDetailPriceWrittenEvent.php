<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductDetailPriceWrittenEvent extends AbstractWrittenEvent
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
