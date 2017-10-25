<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Searcher;

use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductMediaSearchResult extends ProductMediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
