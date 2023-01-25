<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\Subscriber\UpdatePostFinishSubscriber;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * @internal
 */
#[Package('system-settings')]
class UpdatePostFinishSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = UpdatePostFinishSubscriber::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertArrayHasKey(UpdatePostFinishEvent::class, $events);
        static::assertEquals('updatePostFinishEvent', $events[UpdatePostFinishEvent::class]);
    }

    public function testUpdatePostFinishEvent(): void
    {
        $registry = $this->createMock(TaskRegistry::class);
        $registry->expects(static::once())->method('registerTasks');

        (new UpdatePostFinishSubscriber($registry))->updatePostFinishEvent();
    }
}
