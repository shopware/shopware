<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderDeliveryWrittenEvent extends NestedEvent
{
    const NAME = 'order_delivery.written';

    /**
     * @var string[]
     */
    private $orderDeliveryUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderDeliveryUuids, array $errors = [])
    {
        $this->orderDeliveryUuids = $orderDeliveryUuids;
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
    public function getOrderDeliveryUuids(): array
    {
        return $this->orderDeliveryUuids;
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
