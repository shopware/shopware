<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\ScheduledTask\Subscriber\PluginLifecycleSubscriber;

class PluginLifecycleSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents()
    {
        $events = PluginLifecycleSubscriber::getSubscribedEvents();

        static::assertCount(2, $events);
        static::assertArrayHasKey(PluginPostActivateEvent::NAME, $events);
        static::assertEquals('registerScheduledTasked', $events[PluginPostActivateEvent::NAME]);
        static::assertArrayHasKey(PluginPostDeactivateEvent::NAME, $events);
        static::assertEquals('registerScheduledTasked', $events[PluginPostDeactivateEvent::NAME]);
    }

    public function testRegisterScheduledTasks()
    {
        $registry = $this->createMock(TaskRegistry::class);
        $registry->expects(static::once())
            ->method('registerTasks');

        $subscriber = new PluginLifecycleSubscriber($registry);
        $subscriber->registerScheduledTasked();
    }
}
