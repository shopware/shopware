<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
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
#[CoversClass(NetPriceCalculator::class)]
class NetPriceCalculatorTest extends TestCase
{
    #[DataProvider('referencePriceCalculationProvider')]
    public function testReferencePriceCalculation(?ReferencePriceDefinition $reference, ?ReferencePrice $expected): void
    {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection(), 1);
        $definition->setReferencePriceDefinition($reference);

        $calculator = new NetPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals($expected, $price->getReferencePrice());
    }

    public static function referencePriceCalculationProvider(): \Generator
    {
        yield 'test calculation without reference price' => [
            null,
            null,
        ];

        yield 'test calculation with 0 purchase unit' => [
            new ReferencePriceDefinition(0, 1, 'test'),
            null,
        ];

        yield 'test calculation with 0 reference unit' => [
            new ReferencePriceDefinition(1, 0, 'test'),
            null,
        ];

        yield 'test calculation with all requirements fulfilled' => [
            new ReferencePriceDefinition(1, 1, 'test'),
            new ReferencePrice(100, 1, 1, 'test'),
        ];
    }

    #[DataProvider('regulationPriceCalculationProvider')]
    public function testRegulationPriceCalculation(
        ?float $reference,
        ?RegulationPrice $expected
    ): void {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection(), 1);
        $definition->setRegulationPrice($reference);

        $calculator = new NetPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        static::assertEquals($expected, $price->getRegulationPrice());
    }

    public static function regulationPriceCalculationProvider(): \Generator
    {
        yield 'test calculation without regulation price' => [
            null,
            null,
        ];

        yield 'test calculation with zero regulation price' => [
            0.0,
            null,
        ];

        yield 'test calculation with negative regulation price' => [
            -100.0,
            null,
        ];

        yield 'test calculation with valid regulation price' => [
            200.0,
            RegulationPrice::createFromUnitPrice(100, 200),
        ];
    }

    #[DataProvider('listPriceCalculationProvider')]
    public function testListPriceCalculation(?float $listPriceValue, ?ListPrice $expected): void
    {
        $definition = new QuantityPriceDefinition(100, new TaxRuleCollection(), 1);
        $definition->setListPrice($listPriceValue);

        $calculator = new NetPriceCalculator(new TaxCalculator(), new CashRounding());
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

    public function testListPriceIsRoundedBeforePercentageIsCalculated(): void
    {
        // Regression for issue #16687: list and unit price differ only below the currency
        // precision (50.004 vs 50.00), so no discount may be shown. isCalculated is set
        // explicitly because that is the branch the old rounding guard skipped.
        $definition = new QuantityPriceDefinition(50.00, new TaxRuleCollection(), 1);
        $definition->setIsCalculated(true);
        $definition->setListPrice(50.004);

        $calculator = new NetPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        $listPrice = $price->getListPrice();
        static::assertNotNull($listPrice);
        static::assertSame(50.0, $listPrice->getPrice());
        static::assertSame(0.0, $listPrice->getDiscount());
        static::assertSame(0.0, $listPrice->getPercentage());
    }

    public function testRegulationPriceIsRounded(): void
    {
        // Regression for issue #16687: the regulation price must be rounded to the
        // currency precision as well, also when the definition is already calculated.
        $definition = new QuantityPriceDefinition(50.00, new TaxRuleCollection(), 1);
        $definition->setIsCalculated(true);
        $definition->setRegulationPrice(50.004);

        $calculator = new NetPriceCalculator(new TaxCalculator(), new CashRounding());
        $price = $calculator->calculate($definition, new CashRoundingConfig(2, 0.01, true));

        $regulationPrice = $price->getRegulationPrice();
        static::assertNotNull($regulationPrice);
        static::assertSame(50.0, $regulationPrice->getPrice());
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
