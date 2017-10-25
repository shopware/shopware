<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Searcher;

use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class PriceGroupSearchResult extends PriceGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
