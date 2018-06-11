<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryDetailCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Event\OrderBasicLoadedEvent;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class OrderDeliveryDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var OrderDeliveryDetailCollection
     */
    protected $orderDeliveries;

    public function __construct(OrderDeliveryDetailCollection $orderDeliveries, Context $context)
    {
        $this->context = $context;
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderDeliveries(): OrderDeliveryDetailCollection
    {
        return $this->orderDeliveries;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderDeliveries->getOrders()->count() > 0) {
            $events[] = new OrderBasicLoadedEvent($this->orderDeliveries->getOrders(), $this->context);
        }
        if ($this->orderDeliveries->getShippingAddress()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orderDeliveries->getShippingAddress(), $this->context);
        }
        if ($this->orderDeliveries->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->orderDeliveries->getShippingMethods(), $this->context);
        }
        if ($this->orderDeliveries->getOrderStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->orderDeliveries->getOrderStates(), $this->context);
        }
        if ($this->orderDeliveries->getPositions()->count() > 0) {
            $events[] = new OrderDeliveryPositionBasicLoadedEvent($this->orderDeliveries->getPositions(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
