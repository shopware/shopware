<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderNumberWrittenEvent extends NestedEvent
{
    const NAME = 'order_number.written';

    /**
     * @var string[]
     */
    private $orderNumberUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderNumberUuids, array $errors = [])
    {
        $this->orderNumberUuids = $orderNumberUuids;
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
    public function getOrderNumberUuids(): array
    {
        return $this->orderNumberUuids;
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
