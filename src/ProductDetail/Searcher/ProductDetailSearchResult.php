<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Searcher;

use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductDetailSearchResult extends ProductDetailBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
