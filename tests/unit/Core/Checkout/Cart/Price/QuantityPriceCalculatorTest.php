<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(QuantityPriceCalculator::class)]
class QuantityPriceCalculatorTest extends TestCase
{
    #[DataProvider('priceCalculationWithGrossPricesProvider')]
    public function testPriceCalculationWithGrossPrices(
        CashRoundingConfig $config,
        CalculatedPrice $expected,
        QuantityPriceDefinition $priceDefinition
    ): void {
        $taxCalculator = new TaxCalculator();
        $priceDefinition->setIsCalculated(false);

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, new CashRounding()),
            new NetPriceCalculator($taxCalculator, new CashRounding())
        );

        $context = Generator::createSalesChannelContext();
        $context->setItemRounding($config);

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    #[DataProvider('netPrices')]
    public function testNetPrices(
        CalculatedPrice $expected,
        QuantityPriceDefinition $priceDefinition
    ): void {
        $taxCalculator = new TaxCalculator();

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, new CashRounding()),
            new NetPriceCalculator($taxCalculator, new CashRounding())
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::any())
            ->method('getItemRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    #[DataProvider('netDeliveryPrices')]
    public function testNetDeliveries(
        CalculatedPrice $expected,
        QuantityPriceDefinition $priceDefinition
    ): void {
        $priceRounding = new CashRounding();

        $taxCalculator = new TaxCalculator();

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $priceRounding),
            new NetPriceCalculator($taxCalculator, $priceRounding)
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::any())
            ->method('getItemRounding')
            ->willReturn(new CashRoundingConfig(2, 0.01, true));
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_FREE);

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    /**
     * @return list<array{0: CalculatedPrice, 1: QuantityPriceDefinition}>
     */
    public static function netPrices(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);

        return [
            [
                new CalculatedPrice(13.44, 13.44, new CalculatedTaxCollection([new CalculatedTax(2.55, 19, 13.44)]), $highTaxRules),
                new QuantityPriceDefinition(13.436974789916, $highTaxRules),
            ],
        ];
    }

    /**
     * @return list<array{0: CalculatedPrice, 1: QuantityPriceDefinition}>
     */
    public static function netDeliveryPrices(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);

        return [
            [
                new CalculatedPrice(13.44, 13.44, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new QuantityPriceDefinition(13.436974789916, $highTaxRules),
            ],
        ];
    }

    /**
     * @return list<array{0: CashRoundingConfig, 1: CalculatedPrice, 2: QuantityPriceDefinition}>
     */
    public static function priceCalculationWithGrossPricesProvider(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);
        $lowTaxRuleCollection = new TaxRuleCollection([new TaxRule(7)]);

        $rounding = new CashRoundingConfig(2, 0.01, true);
        $threeDecimals = new CashRoundingConfig(3, 0.01, true);

        return [
            [
                $rounding,
                new CalculatedPrice(15.99, 15.99, new CalculatedTaxCollection([new CalculatedTax(2.55, 19, 15.99)]), $highTaxRules),
                new QuantityPriceDefinition(13.436974789916, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(21.32, 21.32, new CalculatedTaxCollection([new CalculatedTax(3.40, 19, 21.32)]), $highTaxRules),
                new QuantityPriceDefinition(17.9159663865546, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(50, 50, new CalculatedTaxCollection([new CalculatedTax(7.98, 19, 50)]), $highTaxRules),
                new QuantityPriceDefinition(42.0168067226891, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(-5.88, -5.88, new CalculatedTaxCollection([new CalculatedTax(-0.94, 19, -5.88)]), $highTaxRules),
                new QuantityPriceDefinition(-4.94117647058824, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(95799.97, 95799.97, new CalculatedTaxCollection([new CalculatedTax(15295.79, 19, 95799.97)]), $highTaxRules),
                new QuantityPriceDefinition(80504.1764705882, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(0.05, 0.05, new CalculatedTaxCollection([new CalculatedTax(0.01, 19, 0.05)]), $highTaxRules),
                new QuantityPriceDefinition(0.0420168067226891, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(0.01, 0.01, new CalculatedTaxCollection([new CalculatedTax(0.00, 19, 0.01)]), $highTaxRules),
                new QuantityPriceDefinition(0.00840336134453782, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(0.08, 0.08, new CalculatedTaxCollection([new CalculatedTax(0.01, 19, 0.08)]), $highTaxRules),
                new QuantityPriceDefinition(0.0672268907563025, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(0.11, 0.11, new CalculatedTaxCollection([new CalculatedTax(0.02, 19, 0.11)]), $highTaxRules),
                new QuantityPriceDefinition(0.092436974789916, $highTaxRules),
            ], [
                $rounding,
                new CalculatedPrice(0.11, 0.11, new CalculatedTaxCollection([new CalculatedTax(0.01, 7, 0.11)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.102803738317757, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(15.99, 15.99, new CalculatedTaxCollection([new CalculatedTax(1.05, 7, 15.99)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(14.9439252336449, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(21.32, 21.32, new CalculatedTaxCollection([new CalculatedTax(1.39, 7, 21.32)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(19.9252336448598, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(50.00, 50.00, new CalculatedTaxCollection([new CalculatedTax(3.27, 7, 50.00)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(46.7289719626168, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(95799.97, 95799.97, new CalculatedTaxCollection([new CalculatedTax(6267.29, 7, 95799.97)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(89532.6822429906, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(0.05, 0.05, new CalculatedTaxCollection([new CalculatedTax(0.00, 7, 0.05)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.0467289719626168, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(0.01, 0.01, new CalculatedTaxCollection([new CalculatedTax(0.00, 7, 0.01)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.00934579439252336, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(0.08, 0.08, new CalculatedTaxCollection([new CalculatedTax(0.01, 7, 0.08)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(0.0747663551401869, $lowTaxRuleCollection),
            ], [
                $rounding,
                new CalculatedPrice(-5.88, -5.88, new CalculatedTaxCollection([new CalculatedTax(-0.38, 7, -5.88)]), $lowTaxRuleCollection),
                new QuantityPriceDefinition(-5.49532710280374, $lowTaxRuleCollection),
            ], [
                $threeDecimals,
                new CalculatedPrice(15.999, 15.999, new CalculatedTaxCollection([new CalculatedTax(2.554, 19, 15.999)]), $highTaxRules),
                new QuantityPriceDefinition(13.4445378151261, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(21.322, 21.322, new CalculatedTaxCollection([new CalculatedTax(3.404, 19, 21.322)]), $highTaxRules),
                new QuantityPriceDefinition(17.9176470588235, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(50.00, 50.00, new CalculatedTaxCollection([new CalculatedTax(7.983, 19, 50.00)]), $highTaxRules),
                new QuantityPriceDefinition(42.01680672268908, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(95799.974, 95799.974, new CalculatedTaxCollection([new CalculatedTax(15295.794, 19, 95799.974)]), $highTaxRules),
                new QuantityPriceDefinition(80504.1798319328, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(0.005, 0.005, new CalculatedTaxCollection([new CalculatedTax(0.001, 19, 0.005)]), $highTaxRules),
                new QuantityPriceDefinition(0.00420168067226891, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(0.001, 0.001, new CalculatedTaxCollection([new CalculatedTax(0.000, 19, 0.001)]), $highTaxRules),
                new QuantityPriceDefinition(0.000840336134453782, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(0.008, 0.008, new CalculatedTaxCollection([new CalculatedTax(0.001, 19, 0.008)]), $highTaxRules),
                new QuantityPriceDefinition(0.00672268907563025, $highTaxRules),
            ], [
                $threeDecimals,
                new CalculatedPrice(-5.988, -5.988, new CalculatedTaxCollection([new CalculatedTax(-0.956, 19, -5.988)]), $highTaxRules),
                new QuantityPriceDefinition(-5.03193277310924, $highTaxRules),
            ],
        ];
    }
}
