<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NestedEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch($event, ?string $eventName = null): object
    {
        if ($event instanceof NestedEvent && $events = $event->getEvents()) {
            foreach ($events as $nested) {
                $name = null;
                if ($nested instanceof GenericEvent) {
                    $name = $nested->getName();
                }
                $this->dispatch($nested, $name);
            }
        }

        return $this->dispatcher->dispatch($event, $eventName);
    }

    public function addListener($eventName, $listener, $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener($eventName, $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function getListeners($eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority($eventName, $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners($eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }
}
