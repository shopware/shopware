<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class CollectingEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<object>
     */
    private array $events = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if ($eventName) {
            $this->events[$eventName] = $event;
        } else {
            $this->events[] = $event;
        }

        return $event;
    }

    /**
     * @return array<object>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
