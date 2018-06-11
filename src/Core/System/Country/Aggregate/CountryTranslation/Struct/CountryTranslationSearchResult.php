<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;

class CountryTranslationSearchResult extends CountryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
