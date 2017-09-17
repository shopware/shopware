<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class PluginWrittenEvent extends NestedEvent
{
    const NAME = 'plugin.written';

    /**
     * @var string[]
     */
    private $pluginUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $pluginUuids, array $errors = [])
    {
        $this->pluginUuids = $pluginUuids;
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
    public function getPluginUuids(): array
    {
        return $this->pluginUuids;
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
