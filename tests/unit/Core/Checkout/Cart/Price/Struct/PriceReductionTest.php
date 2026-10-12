<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceReduction;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceReduction::class)]
class PriceReductionTest extends TestCase
{
    #[DataProvider('discountProvider')]
    public function testDiscount(float $unitPrice, float $referencePrice, float $expected): void
    {
        static::assertSame($expected, PriceReduction::discount($unitPrice, $referencePrice));
    }

    public static function discountProvider(): \Generator
    {
        yield 'saving against the list price / RRP' => [75.0, 100.0, -25.0];
        yield 'saving against the 30-day regulation price' => [75.0, 80.0, -5.0];
        yield 'no saving when prices are equal' => [100.0, 100.0, 0.0];
    }

    #[DataProvider('percentageProvider')]
    public function testPercentage(float $unitPrice, float $referencePrice, float $expected): void
    {
        static::assertSame($expected, PriceReduction::percentage($unitPrice, $referencePrice));
    }

    public static function percentageProvider(): \Generator
    {
        yield 'reduction from the list price / RRP' => [75.0, 100.0, 25.0];
        yield 'reduction from the 30-day regulation price' => [75.0, 80.0, 6.25];
        yield 'no reduction when prices are equal' => [100.0, 100.0, 0.0];
        yield 'rounded to two decimals' => [1.0, 3.0, 66.67];
    }
}
