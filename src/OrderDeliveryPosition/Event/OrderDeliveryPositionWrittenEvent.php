<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderDeliveryPositionWrittenEvent extends NestedEvent
{
    const NAME = 'order_delivery_position.written';

    /**
     * @var string[]
     */
    private $orderDeliveryPositionUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderDeliveryPositionUuids, array $errors = [])
    {
        $this->orderDeliveryPositionUuids = $orderDeliveryPositionUuids;
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
    public function getOrderDeliveryPositionUuids(): array
    {
        return $this->orderDeliveryPositionUuids;
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