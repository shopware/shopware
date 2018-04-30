<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryStateBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CountryStateSearchResult extends CountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
