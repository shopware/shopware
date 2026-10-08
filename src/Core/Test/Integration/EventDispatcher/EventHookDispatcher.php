<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test-environment decorator of the shared event dispatcher that lets a test hook into dispatched events
 * without adding listeners to the dispatcher at runtime, which Symfony 8.2 deprecates for its compiled
 * dispatcher. Hooks run after the dispatcher's own listeners and are skipped once propagation is stopped.
 * It decorates closest to the base dispatcher, so nested events re-dispatched by outer decorators reach it.
 *
 * Tests get it with {@see self::fromContainer()}. The test bootstrap registers a PHPUnit subscriber that clears the
 * hooks before each test.
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

    /**
     * @var \WeakReference<self>|null
     */
    private static ?\WeakReference $current = null;

    public function __construct(private readonly EventDispatcherInterface $inner)
    {
        self::$current = \WeakReference::create($this);
    }

    public static function fromContainer(ContainerInterface $container): self
    {
        $dispatcher = $container->has(self::class) ? $container->get(self::class) : null;
        if (!$dispatcher instanceof self) {
            throw new \LogicException(\sprintf('%s is only registered in the test environment.', self::class));
        }

        return $dispatcher;
    }

    /**
     * Clears the hooks of the dispatcher of the current kernel, if one was built.
     */
    public static function resetCurrent(): void
    {
        self::$current?->get()?->reset();
    }

    public function on(string $eventName, callable $hook, bool $once = false): void
    {
        if (!$once) {
            $this->hooks[$eventName][] = $hook;

            return;
        }

        $onceHook = function (object $event, string $name, self $dispatcher) use ($eventName, $hook, &$onceHook): void {
            $this->off($eventName, $onceHook);
            $hook($event, $name, $dispatcher);
        };
        $this->hooks[$eventName][] = $onceHook;
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
     * The compiled container registers listeners as [service closure, method] arrays, which only become callable
     * once the closure is resolved, so the parameter is widened like Symfony's own dispatcher does.
     *
     * @param callable|array{0: object, 1: string} $listener
     */
    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        /** @var callable $listener the interface declares callable; every dispatcher behind it accepts the array form */
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
