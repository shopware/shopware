<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductBackInStockEvent;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductOutOfStockEvent;
use Shopware\Core\Content\Product\Events\ProductStockChangedEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(StockStorage::class)]
class StockStorageTest extends TestCase
{
    public function testLoadDoesNothing(): void
    {
        $ids = new IdsCollection();

        $productIds = $ids->getList(['p-1', 'p-2', 'p-3']);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());

        static::assertSame(
            [],
            $stockStorage->load(new StockLoadRequest(array_values($productIds)), $salesChannelContext)->all()
        );
    }

    public function testEmptyChangesDoNotDispatchEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->never())->method('dispatch');

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->alter([], Context::createDefaultContext());
    }

    public function testIndexRecomputesAvailabilityWithoutDirectionalEvents(): void
    {
        $outOfStockId = Uuid::randomHex();
        $backInStockId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$outOfStockId => '1', $backInStockId => '0'],
            [$outOfStockId => '0', $backInStockId => '1'],
        );

        $dispatcher = new EventDispatcher();
        $caught = ['legacy' => [], 'directional' => 0];
        $dispatcher->addListener(ProductNoLongerAvailableEvent::class, static function (ProductNoLongerAvailableEvent $event) use (&$caught): void {
            $caught['legacy'][] = $event->getIds();
        });
        $directional = static function () use (&$caught): void {
            ++$caught['directional'];
        };
        $dispatcher->addListener(ProductOutOfStockEvent::class, $directional);
        $dispatcher->addListener(ProductBackInStockEvent::class, $directional);

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->index([$outOfStockId, $backInStockId], $context);

        // index() keeps the legacy availability event (unchanged trunk behaviour) but
        // never emits the directional business events — those belong to alter(), so a
        // freshly created product (0 -> computed) is not announced as back_in_stock
        static::assertCount(1, $caught['legacy']);
        static::assertEqualsCanonicalizing([$outOfStockId, $backInStockId], $caught['legacy'][0]);
        static::assertSame(0, $caught['directional']);
    }

    public function testIndexStaysSilentWhenAvailabilityDidNotFlip(): void
    {
        $productId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$productId => '1'],
            [$productId => '1'],
        );

        $dispatcher = new EventDispatcher();
        $caught = 0;
        $listener = static function () use (&$caught): void {
            ++$caught;
        };
        $dispatcher->addListener(ProductNoLongerAvailableEvent::class, $listener);
        $dispatcher->addListener(ProductOutOfStockEvent::class, $listener);
        $dispatcher->addListener(ProductBackInStockEvent::class, $listener);

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->index([$productId], Context::createDefaultContext());

        static::assertSame(0, $caught);
    }

    public function testAlterDispatchesStockChangedWithDeltaPerAlteration(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($this->createMock(Statement::class));
        $connection->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$productId => '1'],
            [$productId => '1'],
        );
        // the product still exists, so a stock-change event is dispatched
        $connection->method('fetchFirstColumn')->willReturn([$productId]);

        $dispatcher = new EventDispatcher();
        /** @var list<ProductStockChangedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(ProductStockChangedEvent::class, static function (ProductStockChangedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->alter([new StockAlteration(Uuid::randomHex(), $productId, 5, 3)], $context);

        static::assertCount(1, $caught);
        static::assertSame($productId, $caught[0]->getProductId());
        static::assertSame(['stockDelta' => 2], $caught[0]->getStockChange());
    }

    public function testAlterSkipsNoOpStockChanges(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($this->createMock(Statement::class));
        $connection->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$productId => '1'],
            [$productId => '1'],
        );

        $dispatcher = new EventDispatcher();
        $caught = 0;
        $dispatcher->addListener(ProductStockChangedEvent::class, static function () use (&$caught): void {
            ++$caught;
        });

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        // quantityBefore === newQuantity → delta 0 (e.g. only referencedId changed)
        $stockStorage->alter([new StockAlteration(Uuid::randomHex(), $productId, 4, 4)], $context);

        static::assertSame(0, $caught);
    }

    public function testAlterEmitsBothStockChangedAndOutOfStockOnAvailabilityFlip(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($this->createMock(Statement::class));
        // availability flips available → unavailable for the altered product
        $connection->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$productId => '1'],
            [$productId => '0'],
        );
        $connection->method('fetchFirstColumn')->willReturn([$productId]);

        $dispatcher = new EventDispatcher();
        $stockChanged = [];
        $outOfStock = [];
        $dispatcher->addListener(ProductStockChangedEvent::class, static function (ProductStockChangedEvent $event) use (&$stockChanged): void {
            $stockChanged[] = $event->getProductId();
        });
        $dispatcher->addListener(ProductOutOfStockEvent::class, static function (ProductOutOfStockEvent $event) use (&$outOfStock): void {
            $outOfStock[] = $event->getProductId();
        });

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->alter([new StockAlteration(Uuid::randomHex(), $productId, 1, 0)], $context);

        static::assertSame([$productId], $outOfStock);
        static::assertSame([$productId], $stockChanged);
    }

    public function testAlterEmitsBackInStockWhenStockMovementRestoresAvailability(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($this->createMock(Statement::class));
        // an order cancellation returns stock: unavailable -> available
        $connection->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$productId => '0'],
            [$productId => '1'],
        );
        $connection->method('fetchFirstColumn')->willReturn([$productId]);

        $dispatcher = new EventDispatcher();
        $backInStock = [];
        $dispatcher->addListener(ProductBackInStockEvent::class, static function (ProductBackInStockEvent $event) use (&$backInStock): void {
            $backInStock[] = $event->getProductId();
        });

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->alter([new StockAlteration(Uuid::randomHex(), $productId, 0, 1)], $context);

        static::assertSame([$productId], $backInStock);
    }

    public function testAlterDoesNotDispatchStockChangedForDeletedProduct(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($this->createMock(Statement::class));
        $connection->method('fetchAllKeyValue')->willReturn([]);
        // the product row no longer exists (e.g. cancelling an order for a deleted product)
        $connection->method('fetchFirstColumn')->willReturn([]);

        $dispatcher = new EventDispatcher();
        $caught = 0;
        $dispatcher->addListener(ProductStockChangedEvent::class, static function () use (&$caught): void {
            ++$caught;
        });

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->alter([new StockAlteration(Uuid::randomHex(), $productId, 5, 3)], $context);

        static::assertSame(0, $caught);
    }

    public function testAlterSkipsNonLiveVersionContexts(): void
    {
        $connection = $this->createMock(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $versionContext = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $stockStorage = new StockStorage($connection, $dispatcher, $this->createProductRepository());
        $stockStorage->alter([new StockAlteration(Uuid::randomHex(), Uuid::randomHex(), 5, 3)], $versionContext);
    }

    /**
     * @return StaticEntityRepository<ProductCollection>
     */
    private function createProductRepository(): StaticEntityRepository
    {
        $definition = new ProductDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository([], $definition);

        return $repository;
    }
}
