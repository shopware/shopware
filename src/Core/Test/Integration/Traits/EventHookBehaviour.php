<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Traits;

use PHPUnit\Framework\Attributes\After;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hooks into events dispatched on the shared event dispatcher without adding listeners to it at runtime.
 * Hooks run after the dispatcher's own listeners and are cleared after each test.
 *
 * @internal
 */
#[Package('framework')]
trait EventHookBehaviour
{
    private bool $eventHooksUsed = false;

    #[After]
    public function resetEventHooks(): void
    {
        if (!$this->eventHooksUsed) {
            return;
        }

        $this->eventHookDispatcher()->reset();
        $this->eventHooksUsed = false;
    }

    /**
     * The hook receives the event, its name and the dispatcher. With `$once`, it is removed after its first call.
     */
    protected function onEvent(string $eventName, callable $hook, bool $once = false): void
    {
        $this->eventHookDispatcher()->on($eventName, $hook, $once);
    }

    /**
     * The hook receives the event, its name and the dispatcher.
     */
    protected function removeEventHook(string $eventName, callable $hook): void
    {
        $this->eventHookDispatcher()->off($eventName, $hook);
    }

    protected function hookSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventHookDispatcher()->subscribe($subscriber);
    }

    abstract protected static function getContainer(): ContainerInterface;

    private function eventHookDispatcher(): EventHookDispatcher
    {
        $this->eventHooksUsed = true;

        $dispatcher = static::getContainer()->get(EventHookDispatcher::class);
        if (!$dispatcher instanceof EventHookDispatcher) {
            throw new \LogicException(\sprintf('%s is only registered in the test environment.', EventHookDispatcher::class));
        }

        return $dispatcher;
    }
}
