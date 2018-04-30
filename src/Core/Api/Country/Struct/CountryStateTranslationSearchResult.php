<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryStateTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CountryStateTranslationSearchResult extends CountryStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
