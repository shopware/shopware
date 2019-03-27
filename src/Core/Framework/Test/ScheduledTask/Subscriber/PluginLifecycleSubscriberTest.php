<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\ScheduledTask\MessageQueue\RegisterScheduledTaskMessage;
use Shopware\Core\Framework\ScheduledTask\Subscriber\PluginLifecycleSubscriber;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PluginLifecycleSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = PluginLifecycleSubscriber::getSubscribedEvents();

        static::assertCount(2, $events);
        static::assertArrayHasKey(PluginPostActivateEvent::NAME, $events);
        static::assertEquals('registerScheduledTasked', $events[PluginPostActivateEvent::NAME]);
        static::assertArrayHasKey(PluginPostDeactivateEvent::NAME, $events);
        static::assertEquals('registerScheduledTasked', $events[PluginPostDeactivateEvent::NAME]);
    }

    public function testRegisterScheduledTasks(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(static::once())
            ->method('dispatch')->with(static::isInstanceOf(RegisterScheduledTaskMessage::class))
            ->willReturn(new Envelope(new RegisterScheduledTaskMessage()));

        $subscriber = new PluginLifecycleSubscriber($messageBus);
        $subscriber->registerScheduledTasked();
    }
}
