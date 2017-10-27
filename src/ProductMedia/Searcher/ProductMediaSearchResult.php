<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;

class ProductMediaSearchResult extends ProductMediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
