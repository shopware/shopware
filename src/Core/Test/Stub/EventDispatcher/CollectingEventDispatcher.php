<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\EventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @final
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

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
    }

    public function removeListener(string $eventName, callable $listener): void
    {
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
    }

    public function getListeners(?string $eventName = null): array
    {
        return [];
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return null;
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return false;
    }
}
