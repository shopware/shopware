<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderLineItem\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderLineItem\Collection\OrderLineItemDetailCollection;
use Shopware\Checkout\Order\Event\OrderBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderLineItemDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_line_item.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var OrderLineItemDetailCollection
     */
    protected $orderLineItems;

    public function __construct(OrderLineItemDetailCollection $orderLineItems, Context $context)
    {
        $this->context = $context;
        $this->orderLineItems = $orderLineItems;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderLineItems(): OrderLineItemDetailCollection
    {
        return $this->orderLineItems;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderLineItems->getOrders()->count() > 0) {
            $events[] = new OrderBasicLoadedEvent($this->orderLineItems->getOrders(), $this->context);
        }
        if ($this->orderLineItems->getOrderDeliveryPositions()->count() > 0) {
            $events[] = new OrderDeliveryPositionBasicLoadedEvent($this->orderLineItems->getOrderDeliveryPositions(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
