<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;

class AbsolutePriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculateAbsolutePriceOfGrossPricesProvider
     */
    public function testCalculateAbsolutePriceOfGrossPrices(
        float $price,
        CalculatedPrice $expected,
        PriceCollection $prices
    ): void {
        $rounding = new PriceRounding();

        $taxCalculator = new TaxCalculator(
            new TaxRuleCalculator()
        );

        $referencePriceCalculator = new ReferencePriceCalculator($rounding);

        $calculator = new AbsolutePriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
                new NetPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
                Generator::createGrossPriceDetector(),
                $referencePriceCalculator
            ),
            new PercentageTaxRuleBuilder()
        );

        $calculatedPrice = $calculator->calculate(
            $price,
            $prices,
            Generator::createSalesChannelContext()
        );
        static::assertEquals($expected, $calculatedPrice);
        static::assertEquals($expected->getCalculatedTaxes(), $calculatedPrice->getCalculatedTaxes());
        static::assertEquals($expected->getTaxRules(), $calculatedPrice->getTaxRules());
        static::assertEquals($expected->getTotalPrice(), $calculatedPrice->getTotalPrice());
        static::assertEquals($expected->getUnitPrice(), $calculatedPrice->getUnitPrice());
        static::assertEquals($expected->getQuantity(), $calculatedPrice->getQuantity());
    }

    public function calculateAbsolutePriceOfGrossPricesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);

        $taxRules = new TaxRuleCollection([
            new TaxRule(19, 50),
            new TaxRule(7, 50),
        ]);

        //prices of cart line items
        $prices = new PriceCollection([
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(4.79, 19, 30.00)]), $highTax),
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(1.96, 7, 30.00)]), $highTax),
        ]);

        return [
            [
                -6,
                //expected calculated "discount" price
                new CalculatedPrice(
                    -6,
                    -6,
                    new CalculatedTaxCollection([
                        new CalculatedTax(-0.48, 19, -3),
                        new CalculatedTax(-0.20, 7, -3),
                    ]),
                    $taxRules,
                    1
                ),
                $prices,
            ],
        ];
    }

    public function testHundertPercentageDiscount()
    {
        $rounding = new PriceRounding();
        $taxCalculator = new TaxCalculator(new TaxRuleCalculator());
        $referencePriceCalculator = new ReferencePriceCalculator($rounding);

        $priceDefinition = new QuantityPriceDefinition(
            29.00,
            new TaxRuleCollection([new TaxRule(17, 100)]),
            2,
            10,
            true
        );

        $priceCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
            new NetPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
            Generator::createGrossPriceDetector(),
            $referencePriceCalculator
        );

        $absolutePriceCalculator = new AbsolutePriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
                new NetPriceCalculator($taxCalculator, $rounding, $referencePriceCalculator),
                Generator::createGrossPriceDetector(),
                $referencePriceCalculator
            ),
            new PercentageTaxRuleBuilder()
        );

        $percentageCalculator = new PercentagePriceCalculator(
            $rounding,
            new PercentageTaxRuleBuilder()
        );

        $price = $priceCalculator->calculate($priceDefinition, Generator::createSalesChannelContext());

        static::assertEquals(290, $price->getTotalPrice());
        static::assertEquals(29, $price->getUnitPrice());
        static::assertEquals(42.14, $price->getCalculatedTaxes()->get('17')->getTax());

        $discount = $absolutePriceCalculator->calculate(-290, new PriceCollection([$price]), Generator::createSalesChannelContext());

        static::assertEquals(-290, $discount->getTotalPrice());
        static::assertEquals(-290, $discount->getUnitPrice());
        static::assertEquals(-42.14, $discount->getCalculatedTaxes()->get('17')->getTax());

        $discount = $percentageCalculator->calculate(-100, new PriceCollection([$price]), Generator::createSalesChannelContext());
        static::assertEquals(-290, $discount->getTotalPrice());
        static::assertEquals(-290, $discount->getUnitPrice());
        static::assertEquals(-42.14, $discount->getCalculatedTaxes()->get('17')->getTax());
    }
}
