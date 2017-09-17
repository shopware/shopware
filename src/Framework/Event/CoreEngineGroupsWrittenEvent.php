<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreEngineGroupsWrittenEvent extends NestedEvent
{
    const NAME = 'core_engine_groups.written';

    /**
     * @var string[]
     */
    private $coreEngineGroupsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreEngineGroupsUuids, array $errors = [])
    {
        $this->coreEngineGroupsUuids = $coreEngineGroupsUuids;
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
    public function getCoreEngineGroupsUuids(): array
    {
        return $this->coreEngineGroupsUuids;
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
