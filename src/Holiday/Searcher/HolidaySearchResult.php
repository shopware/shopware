<?php declare(strict_types=1);

namespace Shopware\Holiday\Searcher;

use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class HolidaySearchResult extends HolidayBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
