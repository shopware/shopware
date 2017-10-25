<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Searcher;

use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ListingSortingSearchResult extends ListingSortingBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
