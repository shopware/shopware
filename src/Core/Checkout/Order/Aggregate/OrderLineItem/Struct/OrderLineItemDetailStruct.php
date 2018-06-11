<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Core\Checkout\Order\Struct\OrderBasicStruct;

class OrderLineItemDetailStruct extends OrderLineItemBasicStruct
{
    /**
     * @var OrderBasicStruct
     */
    protected $order;

    /**
     * @var OrderDeliveryPositionBasicCollection
     */
    protected $orderDeliveryPositions;

    public function __construct()
    {
        $this->orderDeliveryPositions = new OrderDeliveryPositionBasicCollection();
    }

    public function getOrder(): OrderBasicStruct
    {
        return $this->order;
    }

    public function setOrder(OrderBasicStruct $order): void
    {
        $this->order = $order;
    }

    public function getOrderDeliveryPositions(): OrderDeliveryPositionBasicCollection
    {
        return $this->orderDeliveryPositions;
    }

    public function setOrderDeliveryPositions(OrderDeliveryPositionBasicCollection $orderDeliveryPositions): void
    {
        $this->orderDeliveryPositions = $orderDeliveryPositions;
    }
}
