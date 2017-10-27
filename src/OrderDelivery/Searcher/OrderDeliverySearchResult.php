<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;

class OrderDeliverySearchResult extends OrderDeliveryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
