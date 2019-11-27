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

    public function __construct(DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->definitionRegistry = $definitionRegistry;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getEventNames(): array
    {
        return array_keys($this->events);
    }

    public function getAvailableDataByEvent(string $eventName): array
    {
        return $this->events[$eventName] ?? [];
    }

    public function add(string $event, array $availableData): void
    {
        $this->rawEventData[$event] = $availableData;

        $this->compile();
    }

    public function addMultiple(array $events): void
    {
        foreach ($events as $event => $data) {
            $this->add($event, $data);
        }

        $this->compile();
    }

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
