<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[CoversClass(StockData::class)]
class StockDataTest extends TestCase
{
    public function testAccessors(): void
    {
        $stock = new StockData('12345', 10, true, 2, 5, true);
        $stock->addArrayExtension('extraData', ['foo' => 'bar']);

        static::assertEquals('12345', $stock->productId);
        static::assertEquals(10, $stock->stock);
        static::assertTrue($stock->available);
        static::assertEquals(2, $stock->minPurchase);
        static::assertEquals(5, $stock->maxPurchase);
        static::assertTrue($stock->isCloseout);

        static::assertInstanceOf(ArrayStruct::class, $stock->getExtension('extraData'));
        static::assertEquals(['foo' => 'bar'], $stock->getExtension('extraData')->all());
    }

    public function testDefaultValues(): void
    {
        $stock = new StockData('12345', 10, true);

        static::assertNull($stock->minPurchase);
        static::assertNull($stock->maxPurchase);
        static::assertNull($stock->isCloseout);
    }

    public function testFromArray(): void
    {
        $stock = StockData::fromArray([
            'productId' => '12345',
            'stock' => 10,
            'available' => true,
            'minPurchase' => 2,
            'maxPurchase' => 5,
            'isCloseout' => true,
        ]);

        static::assertEquals('12345', $stock->productId);
        static::assertEquals(10, $stock->stock);
        static::assertTrue($stock->available);
        static::assertEquals(2, $stock->minPurchase);
        static::assertEquals(5, $stock->maxPurchase);
        static::assertTrue($stock->isCloseout);
    }
}
