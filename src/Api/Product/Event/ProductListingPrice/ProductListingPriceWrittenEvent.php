<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductListingPrice;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductListingPriceDefinition;

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
