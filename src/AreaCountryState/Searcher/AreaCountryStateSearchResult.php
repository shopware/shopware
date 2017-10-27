<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;

class AreaCountryStateSearchResult extends AreaCountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
