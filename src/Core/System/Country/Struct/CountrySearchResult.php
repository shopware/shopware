<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountrySearchResult extends CountryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
