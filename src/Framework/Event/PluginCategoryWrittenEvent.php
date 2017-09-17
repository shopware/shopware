<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class PluginCategoryWrittenEvent extends NestedEvent
{
    const NAME = 'plugin_category.written';

    /**
     * @var string[]
     */
    private $pluginCategoryUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $pluginCategoryUuids, array $errors = [])
    {
        $this->pluginCategoryUuids = $pluginCategoryUuids;
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
    public function getPluginCategoryUuids(): array
    {
        return $this->pluginCategoryUuids;
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
