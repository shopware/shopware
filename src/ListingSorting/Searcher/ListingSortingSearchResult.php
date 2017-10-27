<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;

class ListingSortingSearchResult extends ListingSortingBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
