<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;

class CountryStateSearchResult extends CountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
