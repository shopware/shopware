<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class MultiEditQueueWrittenEvent extends NestedEvent
{
    const NAME = 'multi_edit_queue.written';

    /**
     * @var string[]
     */
    private $multiEditQueueUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $multiEditQueueUuids, array $errors = [])
    {
        $this->multiEditQueueUuids = $multiEditQueueUuids;
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
    public function getMultiEditQueueUuids(): array
    {
        return $this->multiEditQueueUuids;
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
