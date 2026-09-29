<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Notification;
use Shopware\Core\Service\Subscriber\ServiceLifecycleSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceLifecycleSubscriber::class)]
class ServiceLifecycleSubscriberTest extends TestCase
{
    public function testSubscribesToCorrectEvents(): void
    {
        static::assertSame([
            NewServicesInstalledEvent::class => 'sendInstalledNotification',
        ], ServiceLifecycleSubscriber::getSubscribedEvents());
    }

    public function testDelegatesAllServicesInstalledEvents(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())->method('createNotification');

        $subscriber = new ServiceLifecycleSubscriber(new Notification($notificationService));
        $subscriber->sendInstalledNotification(new NewServicesInstalledEvent());
    }
}
