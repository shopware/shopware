<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountryTranslationSearchResult extends CountryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
