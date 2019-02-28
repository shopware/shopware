<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

class BusinessEventRegistry
{
    /**
     * @var bool[]
     */
    private $events = [];

    public function getEvents(): array
    {
        return array_keys($this->events);
    }

    public function add(string ...$eventNames): void
    {
        foreach ($eventNames as $event) {
            $this->events[$event] = true;
        }

        ksort($this->events);
    }
}
