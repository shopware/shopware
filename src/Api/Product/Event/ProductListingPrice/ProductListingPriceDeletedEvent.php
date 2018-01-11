<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductListingPrice;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductListingPriceDefinition;

class ProductListingPriceDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_listing_price.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductListingPriceDefinition::class;
    }
}
