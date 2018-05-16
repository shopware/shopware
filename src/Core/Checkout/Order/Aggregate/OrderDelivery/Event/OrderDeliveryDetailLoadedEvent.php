<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery\Event;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryDetailCollection;
use Shopware\Checkout\Order\Event\OrderBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderDeliveryDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderDeliveryDetailCollection
     */
    protected $orderDeliveries;

    public function __construct(OrderDeliveryDetailCollection $orderDeliveries, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
