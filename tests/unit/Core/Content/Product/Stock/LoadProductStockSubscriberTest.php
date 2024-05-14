<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\LoadProductStockSubscriber;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(LoadProductStockSubscriber::class)]
class LoadProductStockSubscriberTest extends TestCase
{
    public function testStockDataIsAppliedFromStorage(): void
    {
        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $subscriber = new LoadProductStockSubscriber($stockStorage);

        $ids = new IdsCollection();

        $p1 = (new SalesChannelProductEntity())->assign(['id' => $ids->get('product-1')]);
        $p2 = (new SalesChannelProductEntity())->assign(['id' => $ids->get('product-2')]);

        $stock1 = new StockData($ids->get('product-1'), 10, false, 5, null, null);
        $stock1->addArrayExtension('extra', ['arbitrary-data1' => 'foo']);
        $stock2 = new StockData($ids->get('product-2'), 12, true);

        $stockStorage->expects(static::once())
            ->method('load')
            ->willReturn(new StockDataCollection([$stock1, $stock2]));

        $event = new SalesChannelEntityLoadedEvent(
            $this->createMock(SalesChannelProductDefinition::class),
            [$p1, $p2],
            $this->createMock(SalesChannelContext::class)
        );

        $subscriber->salesChannelLoaded($event);

        static::assertEquals(10, $p1->getStock());
        static::assertFalse($p1->getAvailable());
        static::assertEquals(5, $p1->getMinPurchase());
        static::assertTrue($p1->hasExtension('stock_data'));
        static::assertSame($stock1, $p1->getExtension('stock_data'));

        static::assertEquals(12, $p2->getStock());
        static::assertTrue($p2->getAvailable());
        static::assertNull($p2->getMinPurchase());
        static::assertTrue($p2->hasExtension('stock_data'));
        static::assertSame($stock2, $p2->getExtension('stock_data'));
    }

    public function testStockDataIsAppliedFromStorageWithPartialEntities(): void
    {
        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $subscriber = new LoadProductStockSubscriber($stockStorage);

        $ids = new IdsCollection();

        $p1 = (new PartialEntity())->assign(['id' => $ids->get('product-1')]);
        $p2 = (new PartialEntity())->assign(['id' => $ids->get('product-2')]);

        $stock1 = new StockData($ids->get('product-1'), 10, false, 5, null, null);
        $stock1->addArrayExtension('extra', ['arbitrary-data1' => 'foo']);
        $stock2 = new StockData($ids->get('product-2'), 12, true);

        $stockStorage->expects(static::once())
            ->method('load')
            ->willReturn(new StockDataCollection([$stock1, $stock2]));

        $event = new SalesChannelEntityLoadedEvent(
            $this->createMock(SalesChannelProductDefinition::class),
            [$p1, $p2],
            $this->createMock(SalesChannelContext::class)
        );

        $subscriber->salesChannelLoaded($event);

        static::assertEquals(10, $p1->get('stock'));
        static::assertFalse($p1->get('available'));
        static::assertEquals(5, $p1->get('minPurchase'));
        static::assertTrue($p1->hasExtension('stock_data'));
        static::assertSame($stock1, $p1->getExtension('stock_data'));

        static::assertEquals(12, $p2->get('stock'));
        static::assertTrue($p2->get('available'));
        static::assertNull($p2->get('minPurchase'));
        static::assertTrue($p2->hasExtension('stock_data'));
        static::assertSame($stock2, $p2->getExtension('stock_data'));
    }
}
