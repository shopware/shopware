<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Notification;
use Shopware\Core\Service\ScheduledTask\InstallServicesTaskHandler;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testAlwaysDelegatesToManager(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->once())
            ->method('install')
            ->willReturn(['some-service']);

        /** @var StaticEntityRepository<NotificationCollection> $notificationRepo */
        $notificationRepo = new StaticEntityRepository([]);

        $handler = new InstallServicesTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $manager,
            new Notification(new NotificationService($notificationRepo)),
        );

        $handler->run();

        static::assertNotEmpty($notificationRepo->creates[0]);
    }
}
