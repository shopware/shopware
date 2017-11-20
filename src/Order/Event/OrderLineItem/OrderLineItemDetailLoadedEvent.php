<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderLineItem;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Collection\OrderLineItemDetailCollection;
use Shopware\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Order\Event\OrderDeliveryPosition\OrderDeliveryPositionBasicLoadedEvent;

class OrderLineItemDetailLoadedEvent extends NestedEvent
{
    const NAME = 'order_line_item.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderLineItemDetailCollection
     */
    protected $orderLineItems;

    public function __construct(OrderLineItemDetailCollection $orderLineItems, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderLineItems = $orderLineItems;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
