<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Listing\Collection\ListingSortingBasicCollection;

class ListingSortingSearchResult extends ListingSortingBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
