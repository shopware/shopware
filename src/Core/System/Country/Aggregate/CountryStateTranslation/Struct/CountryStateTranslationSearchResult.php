<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation\Struct;

use Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountryStateTranslationSearchResult extends CountryStateTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
