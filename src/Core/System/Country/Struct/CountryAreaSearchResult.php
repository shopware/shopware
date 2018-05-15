<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Collection\CountryAreaBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountryAreaSearchResult extends CountryAreaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
