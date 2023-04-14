<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('core')]
class NestedEventDispatcher implements EventDispatcherInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function dispatch(object $event, ?string $eventName = null): object
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

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    /**
     * @return array<array-key, array<array-key, callable(object): void>|callable(object): void>
     */
    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }
}
