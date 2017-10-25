<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;

class OrderLineItemBasicLoadedEvent extends NestedEvent
{
    const NAME = 'order_line_item.basic.loaded';

    /**
     * @var OrderLineItemBasicCollection
     */
    protected $orderLineItems;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderLineItemBasicCollection $orderLineItems, TranslationContext $context)
    {
        $this->orderLineItems = $orderLineItems;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrderLineItems(): OrderLineItemBasicCollection
    {
        return $this->orderLineItems;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
