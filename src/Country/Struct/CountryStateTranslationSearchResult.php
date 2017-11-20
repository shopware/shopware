<?php declare(strict_types=1);

namespace Shopware\Country\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Country\Collection\CountryStateTranslationBasicCollection;

class CountryStateTranslationSearchResult extends CountryStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
