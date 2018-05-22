<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class OrderDeliverySearchResult extends OrderDeliveryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
