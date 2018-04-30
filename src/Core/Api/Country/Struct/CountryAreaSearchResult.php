<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryAreaBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CountryAreaSearchResult extends CountryAreaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
