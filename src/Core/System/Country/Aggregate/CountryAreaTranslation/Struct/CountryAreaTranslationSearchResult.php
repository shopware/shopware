<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;

class CountryAreaTranslationSearchResult extends CountryAreaTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
