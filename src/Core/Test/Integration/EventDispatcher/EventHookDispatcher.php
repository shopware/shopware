<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test-environment decorator of the shared event dispatcher that lets a test hook into dispatched events
 * without adding listeners to the dispatcher at runtime, which Symfony 8.2 deprecates for its compiled
 * dispatcher. Hooks run after the dispatcher's own listeners and are skipped once propagation is stopped.
 * It decorates closest to the base dispatcher, so nested events re-dispatched by outer decorators reach it.
 *
 * Use it through {@see \Shopware\Core\Test\Integration\Traits\EventHookBehaviour}, which clears the hooks
 * after each test.
 *
 * @internal
 */
#[Package('framework')]
final class EventHookDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, list<callable>>
     */
    private array $hooks = [];

    public function __construct(private readonly EventDispatcherInterface $inner)
    {
    }

    public function on(string $eventName, callable $hook): void
    {
        $this->hooks[$eventName][] = $hook;
    }

    public function off(string $eventName, callable $hook): void
    {
        $this->hooks[$eventName] = array_values(array_filter(
            $this->hooks[$eventName] ?? [],
            static fn (callable $registered): bool => $registered !== $hook,
        ));
    }

    /**
     * Hooks every method the subscriber subscribes to. Priorities are ignored: hooks always run after the
     * dispatcher's own listeners.
     */
    public function subscribe(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventName => $params) {
            foreach (self::methods($params) as $method) {
                $hook = [$subscriber, $method];
                if (!\is_callable($hook)) {
                    throw new \LogicException(\sprintf('%s::%s() is not callable.', $subscriber::class, $method));
                }

                $this->on($eventName, $hook);
            }
        }
    }

    public function reset(): void
    {
        $this->hooks = [];
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $eventName ??= $event::class;
        $event = $this->inner->dispatch($event, $eventName);

        foreach ($this->hooks[$eventName] ?? [] as $hook) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $hook($event, $eventName, $this);
        }

        return $event;
    }

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->inner->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->inner->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->inner->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->inner->getListenerPriority($eventName, $listener);
    }

    /**
     * Code that skips dispatching an event without listeners must still reach the hooks.
     */
    public function hasListeners(?string $eventName = null): bool
    {
        if ($eventName !== null && ($this->hooks[$eventName] ?? []) !== []) {
            return true;
        }

        return $this->inner->hasListeners($eventName);
    }

    /**
     * @param string|array{0: string, 1?: int}|list<array{0: string, 1?: int}> $params
     *
     * @return list<string>
     */
    private static function methods(string|array $params): array
    {
        if (\is_string($params)) {
            return [$params];
        }

        if (\is_string($params[0])) {
            return [$params[0]];
        }

        $methods = [];
        foreach ($params as $listener) {
            if (\is_array($listener)) {
                $methods[] = $listener[0];
            }
        }

        return $methods;
    }
}
