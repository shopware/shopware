<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\System\Country\Collection\CountryStateBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CountryStateSearchResult extends CountryStateBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
