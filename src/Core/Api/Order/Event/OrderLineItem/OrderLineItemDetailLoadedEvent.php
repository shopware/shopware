<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderLineItem;

use Shopware\Api\Order\Collection\OrderLineItemDetailCollection;
use Shopware\Api\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderDeliveryPosition\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderLineItemDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_line_item.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderLineItemDetailCollection
     */
    protected $orderLineItems;

    public function __construct(OrderLineItemDetailCollection $orderLineItems, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderLineItems = $orderLineItems;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
