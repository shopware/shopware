<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Struct;

use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountryTranslationSearchResult extends CountryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
