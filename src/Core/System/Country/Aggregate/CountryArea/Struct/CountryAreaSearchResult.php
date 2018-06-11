<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;

class CountryAreaSearchResult extends CountryAreaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
