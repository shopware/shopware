<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductPrice;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductPriceDefinition;

class ProductPriceWrittenEvent extends WrittenEvent
{
    const NAME = 'product_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductPriceDefinition::class;
    }
}
