<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductPrice;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductPriceDefinition;

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
