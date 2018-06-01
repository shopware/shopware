<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderDeliveryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    public function __construct(OrderDeliveryBasicCollection $orderDeliveries, Context $context)
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

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->orderDeliveries;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderDeliveries->getShippingAddress()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orderDeliveries->getShippingAddress(), $this->context);
        }
        if ($this->orderDeliveries->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->orderDeliveries->getShippingMethods(), $this->context);
        }
        if ($this->orderDeliveries->getOrderStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->orderDeliveries->getOrderStates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
