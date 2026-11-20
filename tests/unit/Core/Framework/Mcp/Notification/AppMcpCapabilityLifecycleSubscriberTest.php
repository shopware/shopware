<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityLifecycleSubscriber;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpCapabilityLifecycleSubscriber::class)]
class AppMcpCapabilityLifecycleSubscriberTest extends TestCase
{
    /**
     * @var list<McpListChangedNotificationSet>
     */
    private array $notified = [];

    public function testSubscribesToAppLifecycleAndAppWriteEvents(): void
    {
        static::assertSame([
            AppActivatedEvent::class => 'onAppActivated',
            AppDeactivatedEvent::class => 'onAppDeactivating',
            AppDeletedEvent::class => 'onAppDeleting',
            AppInstalledEvent::class => 'onAppInstalledOrUpdated',
            AppUpdatedEvent::class => 'onAppInstalledOrUpdated',
            'app.written' => 'onAppWritten',
            'app.deleted' => 'onAppWritten',
        ], AppMcpCapabilityLifecycleSubscriber::getSubscribedEvents());
    }

    public function testNotifiesAllCapabilityTypesOnInstallAndUpdate(): void
    {
        $subscriber = $this->createSubscriber(Uuid::randomHex(), McpListChangedNotificationSet::none());
        $subscriber->onAppInstalledOrUpdated();

        static::assertEquals([new McpListChangedNotificationSet(tools: true, resources: true, prompts: true)], $this->notified);
    }

    public function testNotifiesPersistedCapabilitiesWhenAppIsActivated(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: true, resources: false, prompts: true);

        $subscriber = $this->createSubscriber($appId, $notifications);
        $subscriber->onAppActivated(new AppActivatedEvent(AppFixture::createAppEntity(id: $appId), Context::createDefaultContext()));

        static::assertEquals([$notifications], $this->notified);
    }

    public function testNotifiesDeactivationOnlyOnceTheAppIsStoredAsInactive(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: true, resources: false, prompts: true);

        $subscriber = $this->createSubscriber($appId, $notifications);
        $subscriber->onAppDeactivating(new AppDeactivatedEvent(AppFixture::createAppEntity(id: $appId), Context::createDefaultContext()));

        static::assertSame([], $this->notified, 'The app is still active, so a client would read its capabilities again.');

        $subscriber->onAppWritten($this->appWritten($appId, ['id' => $appId, 'active' => false], EntityWriteResult::OPERATION_UPDATE));
        $subscriber->onAppWritten($this->appWritten($appId, ['id' => $appId, 'active' => false], EntityWriteResult::OPERATION_UPDATE));

        static::assertEquals([$notifications], $this->notified);
    }

    public function testNotifiesRemovalOnlyOnceTheAppRowIsDeleted(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: false, resources: true, prompts: false);

        $subscriber = $this->createSubscriber($appId, $notifications);
        $subscriber->onAppDeleting(new AppDeletedEvent($appId, Context::createDefaultContext()));

        static::assertSame([], $this->notified, 'The app is not deleted yet, so a client would read its capabilities again.');

        $subscriber->onAppWritten(new EntityDeletedEvent(
            'app',
            [new EntityWriteResult($appId, ['id' => $appId], 'app', EntityWriteResult::OPERATION_DELETE)],
            Context::createDefaultContext(),
        ));

        static::assertEquals([$notifications], $this->notified);
    }

    public function testIgnoresWritesThatDoNotRemoveTheRecordedApp(): void
    {
        $appId = Uuid::randomHex();
        $otherAppId = Uuid::randomHex();

        $subscriber = $this->createSubscriber($appId, new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));
        $subscriber->onAppDeactivating(new AppDeactivatedEvent(AppFixture::createAppEntity(id: $appId), Context::createDefaultContext()));
        $subscriber->onAppWritten($this->appWritten($otherAppId, ['id' => $otherAppId, 'active' => false], EntityWriteResult::OPERATION_UPDATE));
        $subscriber->onAppWritten($this->appWritten($appId, ['id' => $appId, 'name' => 'renamed'], EntityWriteResult::OPERATION_UPDATE));

        static::assertSame([], $this->notified);
    }

    public function testDoesNotRecordAppsWithoutCapabilities(): void
    {
        $appId = Uuid::randomHex();

        $subscriber = $this->createSubscriber($appId, McpListChangedNotificationSet::none());
        $subscriber->onAppDeleting(new AppDeletedEvent($appId, Context::createDefaultContext()));
        $subscriber->onAppWritten($this->appWritten($appId, ['id' => $appId], EntityWriteResult::OPERATION_DELETE));

        static::assertSame([], $this->notified);
    }

    public function testResetDropsRecordedRemovals(): void
    {
        $appId = Uuid::randomHex();

        $subscriber = $this->createSubscriber($appId, new McpListChangedNotificationSet(tools: true, resources: true, prompts: true));
        $subscriber->onAppDeleting(new AppDeletedEvent($appId, Context::createDefaultContext()));
        $subscriber->reset();
        $subscriber->onAppWritten($this->appWritten($appId, ['id' => $appId], EntityWriteResult::OPERATION_DELETE));

        static::assertSame([], $this->notified);
    }

    private function createSubscriber(string $appId, McpListChangedNotificationSet $persisted): AppMcpCapabilityLifecycleSubscriber
    {
        $detector = static::createStub(AppMcpCapabilityDetector::class);
        $detector->method('persistedForApp')
            ->willReturnCallback(static fn (string $id) => $id === $appId ? $persisted : McpListChangedNotificationSet::none());

        $notifier = static::createStub(McpListChangedNotifier::class);
        $notifier->method('notify')
            ->willReturnCallback(function (McpListChangedNotificationSet $notifications): void {
                $this->notified[] = $notifications;
            });

        return new AppMcpCapabilityLifecycleSubscriber($detector, $notifier);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appWritten(string $appId, array $payload, string $operation): EntityWrittenEvent
    {
        return new EntityWrittenEvent(
            'app',
            [new EntityWriteResult($appId, $payload, 'app', $operation)],
            Context::createDefaultContext(),
        );
    }
}
