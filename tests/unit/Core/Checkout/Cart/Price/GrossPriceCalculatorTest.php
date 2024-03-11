<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

/**
 * @internal
 */
#[CoversClass(GrossPriceCalculator::class)]
class GrossPriceCalculatorTest extends TestCase
{
    #[DataProvider('referencePriceCalculationProvider')]
    public function testReferencePriceCalculation(?ReferencePriceDefinition $reference, float $price, ?ReferencePrice $expected): void
    {
        $definition = new QuantityPriceDefinition($price, new TaxRuleCollection(), 1);
        $definition->setReferencePriceDefinition($reference);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $result = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals($expected, $result->getReferencePrice());
    }

    public static function referencePriceCalculationProvider(): \Generator
    {
        yield 'test calculation without reference price' => [
            null,
            100,
            null,
        ];

        yield 'test calculation with 0 purchase unit' => [
            new ReferencePriceDefinition(0, 1, 'test'),
            100,
            null,
        ];

        yield 'test calculation with 0 reference unit' => [
            new ReferencePriceDefinition(1, 0, 'test'),
            100,
            null,
        ];

        yield 'test calculation with all requirements fulfilled' => [
            new ReferencePriceDefinition(1, 1, 'test'),
            100,
            new ReferencePrice(100, 1, 1, 'test'),
        ];

        yield 'test calculation with smaller reference unit' => [
            new ReferencePriceDefinition(0.7, 1, 'test'),
            7,
            new ReferencePrice(10, 0.7, 1, 'test'),
        ];

        yield 'test calculation with smaller reference unit and cents' => [
            new ReferencePriceDefinition(0.7, 1, 'test'),
            123.3,
            new ReferencePrice(176.14, 0.7, 1, 'test'),
        ];

        yield 'test calculation with smaller reference unit and rounding' => [
            new ReferencePriceDefinition(0.7, 1, 'test'),
            145.25146,
            new ReferencePrice(207.50, 0.7, 1, 'test'),
        ];
    }

    #[DataProvider('regulationPriceCalculationProvider')]
    public function testRegulationPriceCalculation(
        ?float $reference,
        ?RegulationPrice $expected
    ): void {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection(), 1);
        $definition->setRegulationPrice($reference);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals($expected, $price->getRegulationPrice());
    }

    public static function regulationPriceCalculationProvider(): \Generator
    {
        yield 'test calculation without reference price' => [
            null,
            null,
        ];

        yield 'test calculation with reference price' => [
            100,
            new RegulationPrice(100),
        ];
    }
}
