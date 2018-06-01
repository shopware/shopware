<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;

class CountryStateSearchResult extends CountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
