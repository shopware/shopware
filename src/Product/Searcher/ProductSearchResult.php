<?php declare(strict_types=1);

namespace Shopware\Product\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Product\Struct\ProductBasicCollection;

class ProductSearchResult extends ProductBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
