<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreWidgetsWrittenEvent extends NestedEvent
{
    const NAME = 'core_widgets.written';

    /**
     * @var string[]
     */
    private $coreWidgetsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreWidgetsUuids, array $errors = [])
    {
        $this->coreWidgetsUuids = $coreWidgetsUuids;
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
    public function getCoreWidgetsUuids(): array
    {
        return $this->coreWidgetsUuids;
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
