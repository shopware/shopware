<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class MultiEditFilterWrittenEvent extends NestedEvent
{
    const NAME = 'multi_edit_filter.written';

    /**
     * @var string[]
     */
    private $multiEditFilterUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $multiEditFilterUuids, array $errors = [])
    {
        $this->multiEditFilterUuids = $multiEditFilterUuids;
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
    public function getMultiEditFilterUuids(): array
    {
        return $this->multiEditFilterUuids;
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
