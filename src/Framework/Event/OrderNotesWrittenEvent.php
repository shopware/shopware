<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderNotesWrittenEvent extends NestedEvent
{
    const NAME = 'order_notes.written';

    /**
     * @var string[]
     */
    private $orderNotesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderNotesUuids, array $errors = [])
    {
        $this->orderNotesUuids = $orderNotesUuids;
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
    public function getOrderNotesUuids(): array
    {
        return $this->orderNotesUuids;
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
