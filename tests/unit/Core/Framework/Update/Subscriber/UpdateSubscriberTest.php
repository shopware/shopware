<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Subscriber\UpdateSubscriber;

/**
 * @internal
 */
#[CoversClass(UpdateSubscriber::class)]
class UpdateSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                UpdatePostFinishEvent::class => [
                    ['updateFinishedDone', -9999],
                ],
            ],
            UpdateSubscriber::getSubscribedEvents()
        );
    }

    public function testUpdateSuccessful(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('userId123', 'integrationId321'));
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock
            ->expects($this->once())
            ->method('createNotification')
            ->willReturnCallback(function ($data): void {
                static::assertSame('something to inform' . \PHP_EOL, $data['message']);
            });

        $event = new UpdatePostFinishEvent($context, $version, $version);
        $event->appendPostUpdateMessage('something to inform');

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($event);
    }

    public function testUpdateWithoutMessageGetsSkipped(): void
    {
        $context = Context::createDefaultContext();
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock->expects($this->never())->method('createNotification');

        $event = new UpdatePostFinishEvent($context, $version, $version);

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($event);
    }
}
