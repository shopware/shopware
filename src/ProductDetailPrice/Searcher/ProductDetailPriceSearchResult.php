<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;

class ProductDetailPriceSearchResult extends ProductDetailPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
