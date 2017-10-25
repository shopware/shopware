<?php declare(strict_types=1);

namespace Shopware\ProductStream\Searcher;

use Shopware\ProductStream\Struct\ProductStreamBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductStreamSearchResult extends ProductStreamBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
