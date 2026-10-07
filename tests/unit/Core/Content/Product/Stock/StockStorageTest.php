<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductBecameAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(StockStorage::class)]
class StockStorageTest extends TestCase
{
    public function testLoadDoesNothing(): void
    {
        $ids = new IdsCollection();

        $productIds = $ids->getList(['p-1', 'p-2', 'p-3']);
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $connection = static::createStub(Connection::class);
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $stockStorage = new StockStorage($connection, $dispatcher);

        static::assertSame(
            [],
            $stockStorage->load(new StockLoadRequest(array_values($productIds)), $salesChannelContext)->all()
        );
    }

    public function testEmptyChangesDoNotDispatchEvent(): void
    {
        $connection = static::createStub(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->never())->method('dispatch');

        $stockStorage = new StockStorage($connection, $dispatcher);
        $stockStorage->alter([], Context::createDefaultContext());
    }

    public function testIndexDispatchesEventsByAvailabilityDirection(): void
    {
        $ids = new IdsCollection();
        $dispatcher = new CollectingEventDispatcher();

        $stockStorage = new StockStorage($this->createAvailabilityConnection($ids), $dispatcher);
        $stockStorage->index(array_values($ids->getList(['lost', 'gained', 'unchanged'])), Context::createDefaultContext());

        $noLongerAvailable = $dispatcher->getEventsOfClass(ProductNoLongerAvailableEvent::class);
        static::assertCount(1, $noLongerAvailable);
        static::assertSame([$ids->get('lost')], $noLongerAvailable[0]->getIds());

        $becameAvailable = $dispatcher->getEventsOfClass(ProductBecameAvailableEvent::class);
        static::assertCount(1, $becameAvailable);
        static::assertSame([$ids->get('gained')], $becameAvailable[0]->getIds());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testIndexDispatchesAllAvailabilityChangesAsNoLongerAvailableInLegacyMode(): void
    {
        $ids = new IdsCollection();
        $dispatcher = new CollectingEventDispatcher();

        $stockStorage = new StockStorage($this->createAvailabilityConnection($ids), $dispatcher);
        $stockStorage->index(array_values($ids->getList(['lost', 'gained', 'unchanged'])), Context::createDefaultContext());

        $noLongerAvailable = $dispatcher->getEventsOfClass(ProductNoLongerAvailableEvent::class);
        static::assertCount(1, $noLongerAvailable);
        static::assertSame([$ids->get('lost'), $ids->get('gained')], $noLongerAvailable[0]->getIds());

        $becameAvailable = $dispatcher->getEventsOfClass(ProductBecameAvailableEvent::class);
        static::assertCount(1, $becameAvailable);
        static::assertSame([$ids->get('gained')], $becameAvailable[0]->getIds());
    }

    public function testIndexDispatchesNoEventWithoutAvailabilityChange(): void
    {
        $ids = new IdsCollection();
        $dispatcher = new CollectingEventDispatcher();

        $before = [$ids->get('p-1') => '1', $ids->get('p-2') => '0'];

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($before, $before);

        $stockStorage = new StockStorage($connection, $dispatcher);
        $stockStorage->index(array_keys($before), Context::createDefaultContext());

        static::assertSame([], $dispatcher->getEvents());
    }

    private function createAvailabilityConnection(IdsCollection $ids): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(
            [$ids->get('lost') => '1', $ids->get('gained') => '0', $ids->get('unchanged') => '1'],
            [$ids->get('lost') => '0', $ids->get('gained') => '1', $ids->get('unchanged') => '1'],
        );

        return $connection;
    }
}
