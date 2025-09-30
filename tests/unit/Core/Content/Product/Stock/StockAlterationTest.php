<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Stock\StockAlteration;

/**
 * @internal
 */
#[CoversClass(StockAlteration::class)]
class StockAlterationTest extends TestCase
{
    public function testAccessors(): void
    {
        $alteration = new StockAlteration('12345', '67890', 10, 5);

        static::assertSame('12345', $alteration->lineItemId);
        static::assertSame('67890', $alteration->productId);
        static::assertSame(10, $alteration->quantityBefore);
        static::assertSame(5, $alteration->newQuantity);
        static::assertSame(5, $alteration->quantityDelta());

        $alteration = new StockAlteration('12345', '67890', 3, 10);

        static::assertSame('12345', $alteration->lineItemId);
        static::assertSame('67890', $alteration->productId);
        static::assertSame(3, $alteration->quantityBefore);
        static::assertSame(10, $alteration->newQuantity);
        static::assertSame(-7, $alteration->quantityDelta());
    }
}
