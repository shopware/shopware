<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Notification\Subscriber;

use PHPUnit\Framework\Constraint\TraversableContainsIdentical;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\NotificationService;
use Shopware\Administration\Notification\Subscriber\UpdateSubscriber;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * @internal
 *
 * @covers \Shopware\Administration\Notification\Subscriber\UpdateSubscriber
 */
class UpdateSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                UpdatePostFinishEvent::class => [
                    ['updateFinishedDone', -9999],
                ],
            ],
            UpdateSubscriber::getSubscribedEvents()
        );
    }

    public function testUpdateSucessfull(): void
    {
        $context = Context::createDefaultContext();
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock->expects(static::once())->method('createNotification')->with(
            new TraversableContainsIdentical('Updated successfully to version ' . $version)
        );

        $eventMock = $this->createMock(UpdatePostFinishEvent::class);
        $eventMock->expects(static::any())->method('getContext')->willReturn($context);
        $eventMock->expects(static::any())->method('getNewVersion')->willReturn($version);

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($eventMock);
    }

    public function testUpdateSucessfullAdminScope(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('userId123', 'integrationId321'));
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock->expects(static::once())->method('createNotification')->willReturnCallback(
            function ($data): void {
                static::assertContains('success', $data);
                static::assertContains('userId123', $data);
                static::assertContains('integrationId321', $data);
            }
        );

        $eventMock = $this->createMock(UpdatePostFinishEvent::class);
        $eventMock->expects(static::any())->method('getContext')->willReturn($context);
        $eventMock->expects(static::any())->method('getNewVersion')->willReturn($version);

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($eventMock);
    }

    public function testUpdateWarning(): void
    {
        $context = Context::createDefaultContext();
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock->expects(static::once())->method('createNotification')->willReturnCallback(
            function ($data) use ($version): void {
                static::assertContains('Updated successfully to version ' . $version . \PHP_EOL . 'something to inform', $data);
                static::assertContains('warning', $data);
            }
        );

        $eventMock = $this->createMock(UpdatePostFinishEvent::class);
        $eventMock->expects(static::any())->method('getContext')->willReturn($context);
        $eventMock->expects(static::any())->method('getNewVersion')->willReturn($version);
        $eventMock->expects(static::any())->method('getPostUpdateMessage')->willReturn('something to inform');

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($eventMock);
    }
}
