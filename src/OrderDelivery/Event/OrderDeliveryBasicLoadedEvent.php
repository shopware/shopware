<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;

class OrderDeliveryBasicLoadedEvent extends NestedEvent
{
    const NAME = 'orderDelivery.basic.loaded';

    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderDeliveryBasicCollection $orderDeliveries, TranslationContext $context)
    {
        $this->orderDeliveries = $orderDeliveries;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->orderDeliveries;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderDeliveries->getStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->orderDeliveries->getStates(), $this->context);
        }
        if ($this->orderDeliveries->getShippingAddresses()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orderDeliveries->getShippingAddresses(), $this->context);
        }
        if ($this->orderDeliveries->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->orderDeliveries->getShippingMethods(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
