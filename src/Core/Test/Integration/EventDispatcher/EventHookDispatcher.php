<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lets integration tests hook into dispatched events without adding runtime listeners, which Symfony 8.2
 * deprecates. Hooks run after the listeners, respect stopped propagation and are cleared before each test.
 * Get it with {@see self::fromContainer()}.
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
     * Every dispatcher built in this process, so a reset reaches the shared kernel's dispatcher even after a test
     * booted a kernel of its own.
     *
     * @var list<\WeakReference<self>>
     */
    private static array $instances = [];

    public function __construct(private readonly EventDispatcherInterface $inner)
    {
        self::$instances[] = \WeakReference::create($this);
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
     * Clears the hooks of every live dispatcher and forgets the collected ones.
     */
    public static function resetAll(): void
    {
        $live = [];
        foreach (self::$instances as $reference) {
            $dispatcher = $reference->get();
            if ($dispatcher === null) {
                continue;
            }

            $dispatcher->reset();
            $live[] = $reference;
        }

        self::$instances = $live;
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
     * Hooks every subscribed method; priorities are ignored, hooks always run after the listeners.
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
     * Compiled listeners arrive as [service closure, method] arrays, which Symfony's own dispatcher accepts too.
     *
     * @param callable|array{0: object, 1: string} $listener
     */
    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        /** @var callable $listener the interface only declares callable */
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
