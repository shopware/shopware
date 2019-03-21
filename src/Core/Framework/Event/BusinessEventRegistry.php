<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

class BusinessEventRegistry
{
    /**
     * @var array
     */
    private $events = [];

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
        $this->events[$event] = $availableData;
    }

    public function addMultiple(array $events): void
    {
        foreach ($events as $event => $data) {
            $this->add($event, $data);
        }

        ksort($this->events);
    }
}
