<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Order\Collection\OrderDeliveryBasicCollection;

class OrderAddressDetailStruct extends OrderAddressBasicStruct
{
    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    public function __construct()
    {
        $this->orders = new OrderBasicCollection();

        $this->orderDeliveries = new OrderDeliveryBasicCollection();
    }

    public function getOrders(): OrderBasicCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderBasicCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryBasicCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }
}
