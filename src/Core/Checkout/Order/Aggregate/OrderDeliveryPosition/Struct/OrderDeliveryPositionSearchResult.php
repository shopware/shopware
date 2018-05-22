<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct;

use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderDeliveryPositionSearchResult extends OrderDeliveryPositionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
