<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class OrderDeliverySearchResult extends OrderDeliveryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
