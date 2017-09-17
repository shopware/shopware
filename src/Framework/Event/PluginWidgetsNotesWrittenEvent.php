<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class PluginWidgetsNotesWrittenEvent extends NestedEvent
{
    const NAME = 'plugin_widgets_notes.written';

    /**
     * @var string[]
     */
    private $pluginWidgetsNotesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $pluginWidgetsNotesUuids, array $errors = [])
    {
        $this->pluginWidgetsNotesUuids = $pluginWidgetsNotesUuids;
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
    public function getPluginWidgetsNotesUuids(): array
    {
        return $this->pluginWidgetsNotesUuids;
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
