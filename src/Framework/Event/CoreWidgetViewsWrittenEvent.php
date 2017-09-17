<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreWidgetViewsWrittenEvent extends NestedEvent
{
    const NAME = 'core_widget_views.written';

    /**
     * @var string[]
     */
    private $coreWidgetViewsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreWidgetViewsUuids, array $errors = [])
    {
        $this->coreWidgetViewsUuids = $coreWidgetViewsUuids;
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
    public function getCoreWidgetViewsUuids(): array
    {
        return $this->coreWidgetViewsUuids;
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
