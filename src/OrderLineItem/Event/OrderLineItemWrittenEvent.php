<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderLineItemWrittenEvent extends NestedEvent
{
    const NAME = 'order_line_item.written';

    /**
     * @var string[]
     */
    private $orderLineItemUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderLineItemUuids, array $errors = [])
    {
        $this->orderLineItemUuids = $orderLineItemUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getOrderLineItemUuids(): array
    {
        return $this->orderLineItemUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }
    
    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}