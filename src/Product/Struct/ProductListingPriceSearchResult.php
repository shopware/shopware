<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;

class ProductListingPriceSearchResult extends ProductListingPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
