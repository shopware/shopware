<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreDetailStatesWrittenEvent extends NestedEvent
{
    const NAME = 'core_detail_states.written';

    /**
     * @var string[]
     */
    private $coreDetailStatesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreDetailStatesUuids, array $errors = [])
    {
        $this->coreDetailStatesUuids = $coreDetailStatesUuids;
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
    public function getCoreDetailStatesUuids(): array
    {
        return $this->coreDetailStatesUuids;
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
