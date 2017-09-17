<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterOptionWrittenEvent extends NestedEvent
{
    const NAME = 'filter_option.written';

    /**
     * @var string[]
     */
    private $filterOptionUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterOptionUuids, array $errors = [])
    {
        $this->filterOptionUuids = $filterOptionUuids;
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
    public function getFilterOptionUuids(): array
    {
        return $this->filterOptionUuids;
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
