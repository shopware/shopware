<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CountryAreaTranslationSearchResult extends CountryAreaTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
