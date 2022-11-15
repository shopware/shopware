<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Subscriber;

use Google\Auth\Cache\MemoryCacheItemPool;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\Subscriber\PluginLifecycleSubscriber;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * @internal
 */
class PluginLifecycleSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = PluginLifecycleSubscriber::getSubscribedEvents();

        static::assertCount(3, $events);
        static::assertArrayHasKey(PluginPostActivateEvent::class, $events);
        static::assertEquals('afterPluginStateChange', $events[PluginPostActivateEvent::class]);
        static::assertArrayHasKey(PluginPostDeactivateEvent::class, $events);
        static::assertEquals('afterPluginStateChange', $events[PluginPostDeactivateEvent::class]);
        static::assertArrayHasKey(PluginPostUpdateEvent::class, $events);
        static::assertEquals('afterPluginStateChange', $events[PluginPostUpdateEvent::class]);
    }

    public function testRegisterScheduledTasks(): void
    {
        $taskRegistry = $this->createMock(TaskRegistry::class);
        $taskRegistry->expects(static::once())->method('registerTasks');

        $signalCachePool = new MemoryCacheItemPool();
        $subscriber = new PluginLifecycleSubscriber($taskRegistry, $signalCachePool);
        $subscriber->afterPluginStateChange();

        static::assertTrue($signalCachePool->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
    }
}
