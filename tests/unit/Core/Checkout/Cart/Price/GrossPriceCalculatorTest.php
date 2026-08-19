<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
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

    #[DataProvider('listPriceCalculationProvider')]
    public function testListPriceCalculation(?float $listPriceValue, ?ListPrice $expected): void
    {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection(), 1);
        $definition->setListPrice($listPriceValue);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals($expected, $price->getListPrice());
    }

    public static function listPriceCalculationProvider(): \Generator
    {
        yield 'test calculation without list price' => [
            null,
            null,
        ];

        yield 'test calculation with zero list price' => [
            0.0,
            null,
        ];

        yield 'test calculation with valid list price' => [
            200.0,
            ListPrice::createFromUnitPrice(100, 200),
        ];
    }

    public function testUncalculatedUnitPriceIsGrossedUpFromTheNetValue(): void
    {
        $definition = new QuantityPriceDefinition(10.0, new TaxRuleCollection([new TaxRule(19)]), 2);
        $definition->setIsCalculated(false);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertSame(11.9, $price->getUnitPrice());
        static::assertSame(23.8, $price->getTotalPrice());

        $tax = $price->getCalculatedTaxes()->first();
        static::assertNotNull($tax);
        static::assertSame(3.8, $tax->getTax());
    }

    public function testUncalculatedListPriceIsGrossedUpFromTheNetValue(): void
    {
        $definition = new QuantityPriceDefinition(10.0, new TaxRuleCollection([new TaxRule(19)]), 1);
        $definition->setIsCalculated(false);
        $definition->setListPrice(20.0);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals(ListPrice::createFromUnitPrice(11.9, 23.8), $price->getListPrice());
    }

    public function testUncalculatedRegulationPriceIsGrossedUpFromTheNetValue(): void
    {
        $definition = new QuantityPriceDefinition(10.0, new TaxRuleCollection([new TaxRule(19)]), 1);
        $definition->setIsCalculated(false);
        $definition->setRegulationPrice(20.0);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals(new RegulationPrice(23.8), $price->getRegulationPrice());
    }

    public function testUncalculatedReferencePriceUsesTheDerivedGrossUnitPrice(): void
    {
        $definition = new QuantityPriceDefinition(10.0, new TaxRuleCollection([new TaxRule(19)]), 1);
        $definition->setIsCalculated(false);
        $definition->setReferencePriceDefinition(new ReferencePriceDefinition(0.5, 1, 'liter'));

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals(new ReferencePrice(23.8, 0.5, 1, 'liter'), $price->getReferencePrice());
    }

    public function testDerivedGrossPriceRespectsTheCashRoundingInterval(): void
    {
        $definition = new QuantityPriceDefinition(9.99, new TaxRuleCollection([new TaxRule(19)]), 1);
        $definition->setIsCalculated(false);

        $calculator = new GrossPriceCalculator(new TaxCalculator(), new CashRounding());

        $cents = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));
        static::assertSame(11.89, $cents->getUnitPrice());

        $fiveCents = $calculator->calculate($definition, new CashRoundingConfig(2, 0.05, true));
        static::assertSame(11.9, $fiveCents->getUnitPrice());
    }

    public function testTaxesAreRoundedProperly(): void
    {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection([new TaxRule(19, 48.12345)]), 1);
        $calculator = new NetPriceCalculator(new TaxCalculator(), new CashRounding());

        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertCount(1, $price->getCalculatedTaxes());

        $tax = $price->getCalculatedTaxes()->first();
        static::assertNotNull($tax);

        static::assertSame(19.0, $tax->getTaxRate());
        static::assertSame(48.12, $tax->getPrice());
    }
}
