<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Notification;
use Shopware\Core\Service\Subscriber\NewServicesInstalledSubscriber;

/**
 * @internal
 */
#[CoversClass(NewServicesInstalledSubscriber::class)]
class NewServicesInstalledSubscriberTest extends TestCase
{
    public function testItSubscribesToCorrectEvent(): void
    {
        static::assertSame([
            NewServicesInstalledEvent::class => 'sendNewServicesInstalledNotification',
        ], NewServicesInstalledSubscriber::getSubscribedEvents());
    }

    public function testItDelegatesEventToNotificationService(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create');

        $subscriber = new NewServicesInstalledSubscriber(new Notification(new NotificationService($repository)));

        $subscriber->sendNewServicesInstalledNotification(new NewServicesInstalledEvent());
    }
}
