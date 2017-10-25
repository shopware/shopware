<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Searcher;

use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class OrderDeliveryPositionSearchResult extends OrderDeliveryPositionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
