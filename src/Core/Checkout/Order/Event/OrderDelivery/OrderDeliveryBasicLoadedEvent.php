<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderDelivery;

use Shopware\Checkout\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Checkout\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Checkout\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderDeliveryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    public function __construct(OrderDeliveryBasicCollection $orderDeliveries, ApplicationContext $context)
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
