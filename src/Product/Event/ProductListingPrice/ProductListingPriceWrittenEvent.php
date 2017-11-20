<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductListingPrice;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductListingPriceDefinition;

class ProductListingPriceWrittenEvent extends WrittenEvent
{
    const NAME = 'product_listing_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductListingPriceDefinition::class;
    }
}
