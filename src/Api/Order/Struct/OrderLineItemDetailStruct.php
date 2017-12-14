<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Order\Collection\OrderDeliveryPositionBasicCollection;

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
