<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;

class OrderDeliveryPositionSearchResult extends OrderDeliveryPositionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
