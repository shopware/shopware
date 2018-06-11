<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionDetailStruct;

class OrderDeliveryPositionDetailCollection extends OrderDeliveryPositionBasicCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionDetailStruct[]
     */
    protected $elements = [];

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        return new OrderDeliveryBasicCollection(
            $this->fmap(function (OrderDeliveryPositionDetailStruct $orderDeliveryPosition) {
                return $orderDeliveryPosition->getOrderDelivery();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryPositionDetailStruct::class;
    }
}
