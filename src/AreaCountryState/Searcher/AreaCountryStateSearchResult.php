<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Searcher;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class AreaCountryStateSearchResult extends AreaCountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
