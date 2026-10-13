<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UnusedMediaSubscriber::class)]
class UnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            UnusedMediaSubscriber::getSubscribedEvents()
        );
    }

    public function testNothingIsQueriedWhenTheBatchIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $event = new UnusedMediaSearchEvent([], Context::createDefaultContext());

        (new UnusedMediaSubscriber($connection))->removeUsedMedia($event);

        static::assertSame([], $event->getUnusedIds());
    }
}
