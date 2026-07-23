<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Event\ServiceInstalledEvent;
use Shopware\Core\Service\Event\ServiceUpdatedEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Notification;
use Shopware\Core\Service\Subscriber\ServiceLifecycleSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceLifecycleSubscriber::class)]
class ServiceLifecycleSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testSubscribesToCorrectEvents(): void
    {
        static::assertSame([
            ServiceInstalledEvent::class => 'syncState',
            ServiceUpdatedEvent::class => 'syncState',
            NewServicesInstalledEvent::class => 'sendInstalledNotification',
        ], ServiceLifecycleSubscriber::getSubscribedEvents());
    }

    public function testSyncStateWithServiceInstalledEvent(): void
    {
        $serviceName = 'TestService';
        $event = new ServiceInstalledEvent($serviceName, $this->context);

        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('syncState')
            ->with($serviceName, $this->context);

        $subscriber = new ServiceLifecycleSubscriber($lifecycleManager, new Notification(static::createStub(NotificationService::class)));
        $subscriber->syncState($event);
    }

    public function testSyncStateWithServiceUpdatedEvent(): void
    {
        $serviceName = 'TestService';
        $event = new ServiceUpdatedEvent($serviceName, $this->context);

        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('syncState')
            ->with($serviceName, $this->context);

        $subscriber = new ServiceLifecycleSubscriber($lifecycleManager, new Notification(static::createStub(NotificationService::class)));
        $subscriber->syncState($event);
    }

    public function testDelegatesAllServicesInstalledEvents(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())->method('createNotification');

        $subscriber = new ServiceLifecycleSubscriber(static::createStub(LifecycleManager::class), new Notification($notificationService));
        $subscriber->sendInstalledNotification(new NewServicesInstalledEvent());
    }
}
