<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class OrderDeliveryPositionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_delivery_position.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var OrderDeliveryPositionBasicCollection
     */
    protected $orderDeliveryPositions;

    public function __construct(OrderDeliveryPositionBasicCollection $orderDeliveryPositions, Context $context)
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

    public function getOrderDeliveryPositions(): OrderDeliveryPositionBasicCollection
    {
        return $this->orderDeliveryPositions;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderDeliveryPositions->getOrderLineItems()->count() > 0) {
            $events[] = new OrderLineItemBasicLoadedEvent($this->orderDeliveryPositions->getOrderLineItems(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
