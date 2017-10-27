<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;

class AreaCountrySearchResult extends AreaCountryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
