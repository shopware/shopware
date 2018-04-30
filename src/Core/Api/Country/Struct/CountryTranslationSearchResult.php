<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CountryTranslationSearchResult extends CountryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
