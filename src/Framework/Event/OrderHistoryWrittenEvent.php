<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderHistoryWrittenEvent extends NestedEvent
{
    const NAME = 'order_history.written';

    /**
     * @var string[]
     */
    private $orderHistoryUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderHistoryUuids, array $errors = [])
    {
        $this->orderHistoryUuids = $orderHistoryUuids;
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
    public function getOrderHistoryUuids(): array
    {
        return $this->orderHistoryUuids;
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
