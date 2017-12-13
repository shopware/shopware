<?php declare(strict_types=1);

namespace Shopware\Country\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Country\Collection\CountryStateBasicCollection;

class CountryStateSearchResult extends CountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
