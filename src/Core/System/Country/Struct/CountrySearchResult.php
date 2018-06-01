<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Country\Collection\CountryBasicCollection;

class CountrySearchResult extends CountryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
