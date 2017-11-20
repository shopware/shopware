<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderDelivery;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;

class OrderDeliveryBasicLoadedEvent extends NestedEvent
{
    const NAME = 'order_delivery.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    public function __construct(OrderDeliveryBasicCollection $orderDeliveries, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
        if ($this->orderDeliveries->getOrderStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->orderDeliveries->getOrderStates(), $this->context);
        }
        if ($this->orderDeliveries->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->orderDeliveries->getShippingMethods(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
