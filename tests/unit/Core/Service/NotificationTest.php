<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationDefinition;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\Notification;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(Notification::class)]
class NotificationTest extends TestCase
{
    public function testDelegatesNewServicesInstalledToNotificationService(): void
    {
        /** @var StaticEntityRepository<NotificationCollection> $repo */
        $repo = new StaticEntityRepository([], new NotificationDefinition());

        $notification = new Notification(new NotificationService($repo));

        $notification->newServicesInstalled();

        static::assertNotEmpty($repo->creates);
        static::assertCount(1, $repo->creates);

        $createdNotification = $repo->creates[0][0];

        static::assertTrue(Uuid::isValid($createdNotification['id']));
        static::assertEquals('New services have been installed. Reload your administration to see what\'s new.', $createdNotification['message']);
        static::assertEquals('positive', $createdNotification['status']);
        static::assertTrue($createdNotification['adminOnly']);
        static::assertEquals(['system.plugin_maintain'], $createdNotification['requiredPrivileges']);
    }
}
