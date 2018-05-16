<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicStruct;

class OrderDeliveryPositionDetailStruct extends OrderDeliveryPositionBasicStruct
{
    /**
     * @var OrderDeliveryBasicStruct
     */
    protected $orderDelivery;

    public function getOrderDelivery(): OrderDeliveryBasicStruct
    {
        return $this->orderDelivery;
    }

    public function setOrderDelivery(OrderDeliveryBasicStruct $orderDelivery): void
    {
        $this->orderDelivery = $orderDelivery;
    }
}
