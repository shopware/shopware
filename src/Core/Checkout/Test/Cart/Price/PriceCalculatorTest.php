<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\PriceRoundingInterface;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider priceCalculationWithGrossPricesProvider
     */
    public function testPriceCalculationWithGrossPrices(
        PriceRoundingInterface $priceRounding,
        CalculatedPrice $expected,
        QuantityPriceDefinition $priceDefinition
    ): void {
        $taxCalculator = new TaxCalculator(
            $priceRounding,
            new TaxRuleCalculator($priceRounding)
        );

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $priceRounding),
            new NetPriceCalculator($taxCalculator, $priceRounding),
            Generator::createGrossPriceDetector()
        );

        $lineItemPrice = $calculator->calculate(
            $priceDefinition,
            Generator::createSalesChannelContext()
        );

        static::assertEquals($expected, $lineItemPrice);
    }

    /**
     * @dataProvider netPrices
     */
    public function testNetPrices(
        CalculatedPrice $expected,
        QuantityPriceDefinition $priceDefinition
    ): void {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->willReturn(false);
        $detector->method('isNetDelivery')->willReturn(false);

        $taxCalculator = new TaxCalculator(
            new PriceRounding(),
            new TaxRuleCalculator(new PriceRounding())
        );

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, new PriceRounding()),
            new NetPriceCalculator($taxCalculator, new PriceRounding()),
            $detector
        );

        $context = $this->createMock(SalesChannelContext::class);

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    /**
     * @dataProvider netDeliveryPrices
     */
    public function testNetDeliveries(
        CalculatedPrice $expected,
        QuantityPriceDefinition $priceDefinition
    ): void {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->willReturn(false);
        $detector->method('isNetDelivery')->willReturn(true);

        $taxCalculator = new TaxCalculator(
            new PriceRounding(),
            new TaxRuleCalculator(new PriceRounding())
        );

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, new PriceRounding()),
            new NetPriceCalculator($taxCalculator, new PriceRounding()),
            $detector
        );

        $context = $this->createMock(SalesChannelContext::class);

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    public function netPrices(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);

        return [
            [
                new CalculatedPrice(13.44, 13.44, new CalculatedTaxCollection([new CalculatedTax(2.55, 19, 13.44)]), $highTaxRules),
                new QuantityPriceDefinition(13.436974789916, $highTaxRules, 2),
            ],
        ];
    }

    public function netDeliveryPrices(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);

        return [
            [
                new CalculatedPrice(13.44, 13.44, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new QuantityPriceDefinition(13.436974789916, $highTaxRules, 2),
            ],
        ];
    }

    public function priceCalculationWithGrossPricesProvider(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);
        $lowTaxRuleCollection = new TaxRuleCollection([new TaxRule(7)]);

        return [
            [
                new PriceRounding(),
                new CalculatedPrice(15.99, 15.99, new CalculatedTaxCollection([new CalculatedTax(2.55, 19, 15.99)]), $highTaxRules),
                new QuantityPriceDefinition(13.436974789916, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(21.32, 21.32, new CalculatedTaxCollection([new CalculatedTax(3.40, 19, 21.32)]), $highTaxRules),
                new QuantityPriceDefinition(17.9159663865546, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(50, 50, new CalculatedTaxCollection([new CalculatedTax(7.98, 19, 50)]), $highTaxRules),
                new QuantityPriceDefinition(42.0168067226891, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(-5.88, -5.88, new CalculatedTaxCollection([new CalculatedTax(-0.94, 19, -5.88)]), $highTaxRules),
                new QuantityPriceDefinition(-4.94117647058824, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(95799.97, 95799.97, new CalculatedTaxCollection([new CalculatedTax(15295.79, 19, 95799.97)]), $highTaxRules),
                new QuantityPriceDefinition(80504.1764705882, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.05, 0.05, new CalculatedTaxCollection([new CalculatedTax(0.01, 19, 0.05)]), $highTaxRules),
                new QuantityPriceDefinition(0.0420168067226891, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.01, 0.01, new CalculatedTaxCollection([new CalculatedTax(0.00, 19, 0.01)]), $highTaxRules),
                new QuantityPriceDefinition(0.00840336134453782, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.08, 0.08, new CalculatedTaxCollection([new CalculatedTax(0.01, 19, 0.08)]), $highTaxRules),
                new QuantityPriceDefinition(0.0672268907563025, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.11, 0.11, new CalculatedTaxCollection([new CalculatedTax(0.02, 19, 0.11)]), $highTaxRules),
                new QuantityPriceDefinition(0.092436974789916, $highTaxRules, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.11, 0.11, new CalculatedTaxCollection([new CalculatedTax(0.01, 7, 0.11)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.102803738317757, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(15.99, 15.99, new CalculatedTaxCollection([new CalculatedTax(1.05, 7, 15.99)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(14.9439252336449, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(21.32, 21.32, new CalculatedTaxCollection([new CalculatedTax(1.39, 7, 21.32)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(19.9252336448598, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(50.00, 50.00, new CalculatedTaxCollection([new CalculatedTax(3.27, 7, 50.00)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(46.7289719626168, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(95799.97, 95799.97, new CalculatedTaxCollection([new CalculatedTax(6267.29, 7, 95799.97)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(89532.6822429906, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.05, 0.05, new CalculatedTaxCollection([new CalculatedTax(0.00, 7, 0.05)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.0467289719626168, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.01, 0.01, new CalculatedTaxCollection([new CalculatedTax(0.00, 7, 0.01)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.00934579439252336, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.08, 0.08, new CalculatedTaxCollection([new CalculatedTax(0.01, 7, 0.08)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.0747663551401869, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(-5.88, -5.88, new CalculatedTaxCollection([new CalculatedTax(-0.38, 7, -5.88)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(-5.49532710280374, $lowTaxRuleCollection, 2),
            ], [
                new PriceRounding(),
                new CalculatedPrice(15.999, 15.999, new CalculatedTaxCollection([new CalculatedTax(2.554, 19, 15.999)]), $highTaxRules),
                new QuantityPriceDefinition(13.4445378151261, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(21.322, 21.322, new CalculatedTaxCollection([new CalculatedTax(3.404, 19, 21.322)]), $highTaxRules),
                new QuantityPriceDefinition(17.9176470588235, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(50.00, 50.00, new CalculatedTaxCollection([new CalculatedTax(7.983, 19, 50.00)]), $highTaxRules),
                new QuantityPriceDefinition(42.01680672268908, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(95799.974, 95799.974, new CalculatedTaxCollection([new CalculatedTax(15295.794, 19, 95799.974)]), $highTaxRules),
                new QuantityPriceDefinition(80504.1798319328, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.005, 0.005, new CalculatedTaxCollection([new CalculatedTax(0.001, 19, 0.005)]), $highTaxRules),
                new QuantityPriceDefinition(0.00420168067226891, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.001, 0.001, new CalculatedTaxCollection([new CalculatedTax(0.000, 19, 0.001)]), $highTaxRules),
                new QuantityPriceDefinition(0.000840336134453782, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(0.008, 0.008, new CalculatedTaxCollection([new CalculatedTax(0.001, 19, 0.008)]), $highTaxRules),
                new QuantityPriceDefinition(0.00672268907563025, $highTaxRules, 3),
            ], [
                new PriceRounding(),
                new CalculatedPrice(-5.988, -5.988, new CalculatedTaxCollection([new CalculatedTax(-0.956, 19, -5.988)]), $highTaxRules),
                new QuantityPriceDefinition(-5.03193277310924, $highTaxRules, 3),
            ],
        ];
    }
}
