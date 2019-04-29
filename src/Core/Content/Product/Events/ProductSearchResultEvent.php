<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

class ProductSearchResultEvent extends ProductListingResultEvent
{
    public const NAME = 'product.search.result';
}
