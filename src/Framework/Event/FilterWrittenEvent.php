<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterWrittenEvent extends NestedEvent
{
    const NAME = 'filter.written';

    /**
     * @var string[]
     */
    private $filterUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterUuids, array $errors = [])
    {
        $this->filterUuids = $filterUuids;
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
    public function getFilterUuids(): array
    {
        return $this->filterUuids;
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
