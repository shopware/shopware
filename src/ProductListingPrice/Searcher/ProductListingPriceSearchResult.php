<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Searcher;

use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductListingPriceSearchResult extends ProductListingPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
