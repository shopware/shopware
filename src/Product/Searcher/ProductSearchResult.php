<?php declare(strict_types=1);

namespace Shopware\Product\Searcher;

use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductSearchResult extends ProductBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
