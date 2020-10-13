<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class BusinessEventRegistry
{
    private $rawEventData = [];

    /**
     * @var array
     */
    private $events = [];

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var string[]
     */
    private $classes = [];

    public function __construct(DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->definitionRegistry = $definitionRegistry;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - use `\Shopware\Core\Framework\Event\BusinessEventCollector::collect` instead
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - use `\Shopware\Core\Framework\Event\BusinessEventCollector::collect` instead
     */
    public function getEventNames(): array
    {
        return array_keys($this->events);
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - use `\Shopware\Core\Framework\Event\BusinessEventCollector::collect` instead
     */
    public function getAvailableDataByEvent(string $eventName): array
    {
        return $this->events[$eventName] ?? [];
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - use `addClasses` instead
     */
    public function add(string $event, array $availableData): void
    {
        $this->rawEventData[$event] = $availableData;

        $this->compile();
    }

    public function addClasses(array $classes): void
    {
        $this->classes = array_unique(array_merge($this->classes, $classes));
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - use `addClasses` instead
     */
    public function addMultiple(array $events): void
    {
        foreach ($events as $event => $data) {
            $this->add($event, $data);
        }

        $this->compile();
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - will be removed
     */
    private function compile(): void
    {
        foreach ($this->rawEventData as $eventName => $eventData) {
            $compiledEventData = [];

            foreach ($eventData as $key => $data) {
                if (!in_array($data['type'], ['collection', 'entity'], true)) {
                    $compiledEventData[$key] = $data;

                    continue;
                }

                $compiledEventData[$key] = [
                    'type' => $data['type'],
                    'entity' => $this->definitionRegistry->get($data['entityClass'])->getEntityName(),
                ];
            }

            $this->events[$eventName] = $compiledEventData;
        }

        $this->rawEventData = [];
        ksort($this->events);
    }
}
