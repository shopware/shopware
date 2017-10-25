<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Searcher;

use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class OrderDeliverySearchResult extends OrderDeliveryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
