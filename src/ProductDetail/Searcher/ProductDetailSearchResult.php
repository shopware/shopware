<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;

class ProductDetailSearchResult extends ProductDetailBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
