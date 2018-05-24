<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;

class CountryTranslationSearchResult extends CountryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
