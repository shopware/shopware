<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;

/**
 * @internal
 */
#[CoversClass(StockDataCollection::class)]
class StockDataCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $collection = new StockDataCollection([]);

        static::assertEmpty($collection->all());
    }

    public function testGetStockForProductId(): void
    {
        $collection = new StockDataCollection([]);

        static::assertNull($collection->getStockForProductId('12345'));

        $stock1 = new StockData('12345', 10, true);
        $collection = new StockDataCollection([
            $stock1,
        ]);

        static::assertSame($stock1, $collection->getStockForProductId('12345'));
        static::assertNull($collection->getStockForProductId('23456'));
    }

    public function testAdd(): void
    {
        $collection = new StockDataCollection([]);

        static::assertEmpty($collection->all());

        $stock1 = new StockData('12345', 10, true);

        $collection->add($stock1);

        static::assertCount(1, $collection->all());
        static::assertSame($stock1, $collection->getStockForProductId('12345'));
    }
}
