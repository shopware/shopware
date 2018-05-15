<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountryAreaTranslationSearchResult extends CountryAreaTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
