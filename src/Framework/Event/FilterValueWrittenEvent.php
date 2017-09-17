<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterValueWrittenEvent extends NestedEvent
{
    const NAME = 'filter_value.written';

    /**
     * @var string[]
     */
    private $filterValueUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterValueUuids, array $errors = [])
    {
        $this->filterValueUuids = $filterValueUuids;
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
    public function getFilterValueUuids(): array
    {
        return $this->filterValueUuids;
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
