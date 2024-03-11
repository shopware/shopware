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

        static::assertEquals('12345', $alteration->lineItemId);
        static::assertEquals('67890', $alteration->productId);
        static::assertEquals(10, $alteration->quantityBefore);
        static::assertEquals(5, $alteration->newQuantity);
        static::assertEquals(5, $alteration->quantityDelta());

        $alteration = new StockAlteration('12345', '67890', 3, 10);

        static::assertEquals('12345', $alteration->lineItemId);
        static::assertEquals('67890', $alteration->productId);
        static::assertEquals(3, $alteration->quantityBefore);
        static::assertEquals(10, $alteration->newQuantity);
        static::assertEquals(-7, $alteration->quantityDelta());
    }
}
