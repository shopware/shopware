<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppsUpdatedEvent;
use Shopware\Core\Framework\App\Subscriber\AppPrivilegesRequestedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(AppPrivilegesRequestedSubscriber::class)]
class AppPrivilegesRequestedSubscriberTest extends TestCase
{
    private NotificationService&MockObject $notificationService;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
    }

    public function testNotificationIsNotCreatedWhenNoAppsAreUpdated(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $this->notificationService->expects($this->never())
            ->method('createNotification');

        $subscriber = new AppPrivilegesRequestedSubscriber($appRepository, $this->notificationService);

        $event = new AppsUpdatedEvent([], Context::createDefaultContext());
        $subscriber->onAppsUpdated($event);
    }

    public function testNotificationIsNotCreatedWhenNoPrivilegesRequested(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = new AppEntity();
        $app->setId($appId);
        $app->setRequestedPrivileges([]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$app])]);

        $this->notificationService->expects($this->never())
            ->method('createNotification');

        $subscriber = new AppPrivilegesRequestedSubscriber($appRepository, $this->notificationService);

        $event = new AppsUpdatedEvent([$appId], $context);
        $subscriber->onAppsUpdated($event);
    }

    public function testNotificationIsCreatedWhenAppsRequestPrivileges(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = new AppEntity();
        $app->setId($appId);
        $app->setRequestedPrivileges(['read:product', 'write:product']);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$app])]);

        $this->notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                static::callback(function (array $data) {
                    return isset($data['id'])
                        && $data['status'] === 'warning'
                        && $data['message'] === 'notification.privileges.requested'
                        && $data['adminOnly'] === true
                        && $data['requiredPrivileges'] === [];
                }),
                $context
            );

        $subscriber = new AppPrivilegesRequestedSubscriber($appRepository, $this->notificationService);

        $event = new AppsUpdatedEvent([$appId], $context);
        $subscriber->onAppsUpdated($event);
    }
}
