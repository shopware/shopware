<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\Subscriber\RepairDigitalProductStatesSubscriber;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;

/**
 * @internal
 */
#[CoversClass(RepairDigitalProductStatesSubscriber::class)]
class RepairDigitalProductStatesSubscriberTest extends TestCase
{
    public function testRepairReturnsEarlyWhenAlreadyMarked(): void
    {
        $storage = new ArrayKeyValueStorage([
            RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES => '1',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->never())->method('update');

        $subscriber = new RepairDigitalProductStatesSubscriber(
            $connection,
            $statesUpdater,
            $storage,
            $this->createMock(LoggerInterface::class),
        );

        $subscriber->repair($this->createEvent());

        static::assertTrue($storage->has(RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES));
        static::assertSame('1', $storage->get(RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES));
    }

    public function testRepairUpdatesProductsAndSetsMarker(): void
    {
        $ids = ['a', 'b'];
        $event = $this->createEvent();

        $storage = new ArrayKeyValueStorage();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($ids);

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->once())
            ->method('update')
            ->with($ids, $event->getContext());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $subscriber = new RepairDigitalProductStatesSubscriber(
            $connection,
            $statesUpdater,
            $storage,
            $logger,
        );

        $subscriber->repair($event);

        static::assertStringContainsString('Repaired product states for 2 digital product(s)', $event->getPostUpdateMessage());
        static::assertTrue($storage->has(RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES));
        static::assertSame('1', $storage->get(RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES));
    }

    public function testRepairAbortsWhenSameChunkIsFetchedTwice(): void
    {
        $ids = array_map(static fn (int $i): string => 'id-' . $i, range(1, 500));
        $event = $this->createEvent();

        $storage = new ArrayKeyValueStorage();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls($ids, $ids);

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->once())
            ->method('update')
            ->with($ids, $event->getContext());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('same product chunk was fetched repeatedly'),
                static::arrayHasKey('productIds')
            );

        $subscriber = new RepairDigitalProductStatesSubscriber(
            $connection,
            $statesUpdater,
            $storage,
            $logger,
        );

        $subscriber->repair($event);

        static::assertStringContainsString('Repaired product states for 500 digital product(s)', $event->getPostUpdateMessage());
        static::assertTrue($storage->has(RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES));
        static::assertSame('1', $storage->get(RepairDigitalProductStatesSubscriber::REPAIRED_DIGITAL_PRODUCT_STATES));
    }

    private function createEvent(): UpdatePostFinishEvent
    {
        return new UpdatePostFinishEvent(new Context(new SystemSource()), '6.7.8.0', '6.7.8.1');
    }
}
