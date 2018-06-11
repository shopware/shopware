<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionDetailCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class OrderDeliveryPositionDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery_position.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionDetailCollection
     */
    protected $orderDeliveryPositions;

    public function __construct(OrderDeliveryPositionDetailCollection $orderDeliveryPositions, Context $context)
    {
        $this->context = $context;
        $this->orderDeliveryPositions = $orderDeliveryPositions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderDeliveryPositions(): OrderDeliveryPositionDetailCollection
    {
        return $this->orderDeliveryPositions;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderDeliveryPositions->getOrderDeliveries()->count() > 0) {
            $events[] = new OrderDeliveryBasicLoadedEvent($this->orderDeliveryPositions->getOrderDeliveries(), $this->context);
        }
        if ($this->orderDeliveryPositions->getOrderLineItems()->count() > 0) {
            $events[] = new OrderLineItemBasicLoadedEvent($this->orderDeliveryPositions->getOrderLineItems(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
