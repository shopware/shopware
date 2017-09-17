<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderDetailsWrittenEvent extends NestedEvent
{
    const NAME = 'order_details.written';

    /**
     * @var string[]
     */
    private $orderDetailsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderDetailsUuids, array $errors = [])
    {
        $this->orderDetailsUuids = $orderDetailsUuids;
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
    public function getOrderDetailsUuids(): array
    {
        return $this->orderDetailsUuids;
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
