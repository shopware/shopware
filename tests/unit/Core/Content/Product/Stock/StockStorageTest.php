<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    public function testAlterRetriesMariaDbRecordChangedExceptionOutsideTransaction(): void
    {
        $productId = Uuid::randomHex();
        $recordChangedException = new DriverException(
            new PdoException('Record has changed since last read', 'HY000', 1020),
            null,
        );
        $attempts = 0;

        $statement = $this->createMock(Statement::class);
        $statement->expects($this->exactly(6))->method('bindValue');
        $statement->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function () use (&$attempts, $recordChangedException): int {
                ++$attempts;

                if ($attempts === 1) {
                    throw $recordChangedException;
                }

                return 1;
            });

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('prepare')->willReturn($statement);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects($this->exactly(2))
            ->method('fetchAllKeyValue')
            ->willReturn([$productId => 1]);
        $connection->expects($this->once())->method('executeStatement')->willReturn(1);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ProductStockAlteredEvent::class));

        $stockStorage = new StockStorage($connection, $dispatcher);
        $stockStorage->alter([
            new StockAlteration(Uuid::randomHex(), $productId, 1, 0),
        ], Context::createDefaultContext());

        static::assertSame(2, $attempts);
    }
}
