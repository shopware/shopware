<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Searcher;

use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductDetailPriceSearchResult extends ProductDetailPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
