<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Collection\OrderLineItemDetailCollection;
use Shopware\Core\Checkout\Order\Event\OrderBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class OrderLineItemDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_line_item.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
