<?php declare(strict_types=1);

namespace Shopware\Holiday\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Holiday\Struct\HolidayBasicCollection;

class HolidaySearchResult extends HolidayBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
