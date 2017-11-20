<?php declare(strict_types=1);

namespace Shopware\Country\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Country\Collection\CountryBasicCollection;

class CountrySearchResult extends CountryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
