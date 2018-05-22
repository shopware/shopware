<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;

class CountryAreaSearchResult extends CountryAreaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
