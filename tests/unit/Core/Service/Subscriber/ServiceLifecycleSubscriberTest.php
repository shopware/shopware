<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
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
#[CoversClass(ServiceLifecycleSubscriber::class)]
class ServiceLifecycleSubscriberTest extends TestCase
{
    private LifecycleManager&MockObject $lifecycleManager;

    private NotificationService&MockObject $notificationService;

    private ServiceLifecycleSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->lifecycleManager = $this->createMock(LifecycleManager::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->subscriber = new ServiceLifecycleSubscriber($this->lifecycleManager, new Notification($this->notificationService));
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

        $this->lifecycleManager->expects($this->once())
            ->method('syncState')
            ->with($serviceName, $this->context);

        $this->subscriber->syncState($event);
    }

    public function testSyncStateWithServiceUpdatedEvent(): void
    {
        $serviceName = 'TestService';
        $event = new ServiceUpdatedEvent($serviceName, $this->context);

        $this->lifecycleManager->expects($this->once())
            ->method('syncState')
            ->with($serviceName, $this->context);

        $this->subscriber->syncState($event);
    }

    public function delegatesAllServicesInstalledEvents(): void
    {
        $this->notificationService->expects($this->once())->method('createNotification');

        $this->subscriber->sendInstalledNotification(new NewServicesInstalledEvent());
    }
}
