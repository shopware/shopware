<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class PluginRecommendationsWrittenEvent extends NestedEvent
{
    const NAME = 'plugin_recommendations.written';

    /**
     * @var string[]
     */
    private $pluginRecommendationsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $pluginRecommendationsUuids, array $errors = [])
    {
        $this->pluginRecommendationsUuids = $pluginRecommendationsUuids;
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
    public function getPluginRecommendationsUuids(): array
    {
        return $this->pluginRecommendationsUuids;
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
