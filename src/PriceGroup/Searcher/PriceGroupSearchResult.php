<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;

class PriceGroupSearchResult extends PriceGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
