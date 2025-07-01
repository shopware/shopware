<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Message\InstallServicesMessage;
use Shopware\Core\Service\MessageHandler\InstallServicesHandler;
use Shopware\Core\Service\Notification;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(InstallServicesHandler::class)]
class InstallServicesHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('install')
            ->with(static::isInstanceOf(Context::class))
            ->willReturn(['some-cool-service']);

        /** @var StaticEntityRepository<NotificationCollection> $notificationRepo */
        $notificationRepo = new StaticEntityRepository([]);

        $handler = new InstallServicesHandler(
            $lifecycleManager,
            new Notification(new NotificationService($notificationRepo))
        );
        $handler->__invoke(new InstallServicesMessage());

        static::assertNotEmpty($notificationRepo->creates[0]);
    }
}
