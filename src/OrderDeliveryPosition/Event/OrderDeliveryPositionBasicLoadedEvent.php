<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;
use Shopware\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;

class OrderDeliveryPositionBasicLoadedEvent extends NestedEvent
{
    const NAME = 'orderDeliveryPosition.basic.loaded';

    /**
     * @var OrderDeliveryPositionBasicCollection
     */
    protected $orderDeliveryPositions;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderDeliveryPositionBasicCollection $orderDeliveryPositions, TranslationContext $context)
    {
        $this->orderDeliveryPositions = $orderDeliveryPositions;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrderDeliveryPositions(): OrderDeliveryPositionBasicCollection
    {
        return $this->orderDeliveryPositions;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderDeliveryPositions->getLineItems()->count() > 0) {
            $events[] = new OrderLineItemBasicLoadedEvent($this->orderDeliveryPositions->getLineItems(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
