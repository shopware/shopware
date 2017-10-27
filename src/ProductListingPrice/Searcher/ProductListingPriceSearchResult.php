<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;

class ProductListingPriceSearchResult extends ProductListingPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
