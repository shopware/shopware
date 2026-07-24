<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityLifecycleSubscriber;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpCapabilityLifecycleSubscriber::class)]
class AppMcpCapabilityLifecycleSubscriberTest extends TestCase
{
    public function testSubscribesToAppLifecycleEvents(): void
    {
        static::assertSame([
            AppActivatedEvent::class => 'onAppChanged',
            AppDeactivatedEvent::class => 'onAppChanged',
            AppDeletedEvent::class => 'onAppDeleted',
            AppInstalledEvent::class => 'onAppFeaturesChanged',
            AppUpdatedEvent::class => 'onAppFeaturesChanged',
        ], AppMcpCapabilityLifecycleSubscriber::getSubscribedEvents());
    }

    public function testNotifiesAllCapabilitiesAfterAppFeaturesChanged(): void
    {
        $detector = static::createStub(AppMcpCapabilityDetector::class);
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with(static::callback(static fn (McpListChangedNotificationSet $set): bool => $set->tools && $set->resources && $set->prompts));

        $subscriber = new AppMcpCapabilityLifecycleSubscriber($detector, $notifier);
        $subscriber->onAppFeaturesChanged();
    }

    public function testNotifiesPersistedCapabilitiesForChangedApp(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: true, resources: false, prompts: true);

        $detector = $this->createMock(AppMcpCapabilityDetector::class);
        $detector->expects($this->once())
            ->method('persistedForApp')
            ->with($appId)
            ->willReturn($notifications);

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($notifications);

        $subscriber = new AppMcpCapabilityLifecycleSubscriber($detector, $notifier);
        $subscriber->onAppChanged(new AppActivatedEvent((new AppEntity())->assign(['id' => $appId]), Context::createDefaultContext()));
    }

    public function testNotifiesPersistedCapabilitiesForDeletedApp(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: false, resources: true, prompts: false);

        $detector = $this->createMock(AppMcpCapabilityDetector::class);
        $detector->expects($this->once())
            ->method('persistedForApp')
            ->with($appId)
            ->willReturn($notifications);

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($notifications);

        $subscriber = new AppMcpCapabilityLifecycleSubscriber($detector, $notifier);
        $subscriber->onAppDeleted(new AppDeletedEvent($appId, Context::createDefaultContext()));
    }
}
