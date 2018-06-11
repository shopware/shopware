<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Core\Checkout\Order\Collection\OrderBasicCollection;

class OrderAddressDetailStruct extends OrderAddressBasicStruct
{
    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection
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
