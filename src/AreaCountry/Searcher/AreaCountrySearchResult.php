<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Searcher;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class AreaCountrySearchResult extends AreaCountryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
