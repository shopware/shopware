<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Cart\Test\Domain\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Price\PriceRounding;
use Shopware\Cart\Tax\CalculatedTax;
use Shopware\Cart\Tax\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\Cart\Tax\TaxRule;
use Shopware\Cart\Tax\TaxRuleCalculator;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Cart\Test\Common\Generator;

/**
 * Class PriceCalculatorTest
 */
class PriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider priceCalculationWithGrossPricesProvider
     *
     * @param PriceRounding   $priceRounding
     * @param Price           $expected
     * @param PriceDefinition $priceDefinition
     */
    public function testPriceCalculationWithGrossPrices(
        PriceRounding $priceRounding,
        Price $expected,
        PriceDefinition $priceDefinition
    ): void {
        $calculator = new PriceCalculator(
            new TaxCalculator(
                $priceRounding,
                [new TaxRuleCalculator($priceRounding)]
            ),
            $priceRounding,
            Generator::createGrossPriceDetector()
        );

        $lineItemPrice = $calculator->calculate(
            $priceDefinition,
            Generator::createContext()
        );

        static::assertEquals($expected, $lineItemPrice);
    }

    /**
     * @dataProvider netPrices
     *
     * @param Price           $expected
     * @param PriceDefinition $priceDefinition
     */
    public function testNetPrices(
        Price $expected,
        PriceDefinition $priceDefinition
    ): void {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->will($this->returnValue(false));
        $detector->method('isNetDelivery')->will($this->returnValue(false));

        $calculator = new PriceCalculator(
            new TaxCalculator(
                new PriceRounding(2),
                [new TaxRuleCalculator(new PriceRounding(2))]
            ),
            new PriceRounding(2),
            $detector
        );

        $context = $this->createMock(ShopContext::class);

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    /**
     * @dataProvider netDeliveryPrices
     *
     * @param Price           $expected
     * @param PriceDefinition $priceDefinition
     */
    public function testNetDeliveries(
        Price $expected,
        PriceDefinition $priceDefinition
    ): void {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->will($this->returnValue(false));
        $detector->method('isNetDelivery')->will($this->returnValue(true));

        $calculator = new PriceCalculator(
            new TaxCalculator(
                new PriceRounding(2),
                [new TaxRuleCalculator(new PriceRounding(2))]
            ),
            new PriceRounding(2),
            $detector
        );

        $context = $this->createMock(ShopContext::class);

        $lineItemPrice = $calculator->calculate($priceDefinition, $context);

        static::assertEquals($expected, $lineItemPrice);
    }

    public function netPrices(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);

        return [
            [
                new Price(13.44, 13.44, new CalculatedTaxCollection([new CalculatedTax(2.55, 19, 13.44)]), $highTaxRules),
                new PriceDefinition(13.436974789916, $highTaxRules),
            ],
        ];
    }

    public function netDeliveryPrices(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);

        return [
            [
                new Price(13.44, 13.44, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new PriceDefinition(13.436974789916, $highTaxRules),
            ],
        ];
    }

    public function priceCalculationWithGrossPricesProvider(): array
    {
        $highTaxRules = new TaxRuleCollection([new TaxRule(19)]);
        $lowTaxRuleCollection = new TaxRuleCollection([new TaxRule(7)]);

        return [
            [
                new PriceRounding(2),
                new Price(15.99, 15.99, new CalculatedTaxCollection([new CalculatedTax(2.55, 19, 15.99)]), $highTaxRules),
                new PriceDefinition(13.436974789916, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(21.32, 21.32, new CalculatedTaxCollection([new CalculatedTax(3.40, 19, 21.32)]), $highTaxRules),
                new PriceDefinition(17.9159663865546, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(50, 50, new CalculatedTaxCollection([new CalculatedTax(7.98, 19, 50)]), $highTaxRules),
                new PriceDefinition(42.0168067226891, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(-5.88, -5.88, new CalculatedTaxCollection([new CalculatedTax(-0.94, 19, -5.88)]), $highTaxRules),
                new PriceDefinition(-4.94117647058824, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(95799.97, 95799.97, new CalculatedTaxCollection([new CalculatedTax(15295.79, 19, 95799.97)]), $highTaxRules),
                new PriceDefinition(80504.1764705882, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(0.05, 0.05, new CalculatedTaxCollection([new CalculatedTax(0.01, 19, 0.05)]), $highTaxRules),
                new PriceDefinition(0.0420168067226891, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(0.01, 0.01, new CalculatedTaxCollection([new CalculatedTax(0.00, 19, 0.01)]), $highTaxRules),
                new PriceDefinition(0.00840336134453782, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(0.08, 0.08, new CalculatedTaxCollection([new CalculatedTax(0.01, 19, 0.08)]), $highTaxRules),
                new PriceDefinition(0.0672268907563025, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(0.11, 0.11, new CalculatedTaxCollection([new CalculatedTax(0.02, 19, 0.11)]), $highTaxRules),
                new PriceDefinition(0.092436974789916, $highTaxRules),
            ], [
                new PriceRounding(2),
                new Price(0.11, 0.11, new CalculatedTaxCollection([new CalculatedTax(0.01, 7, 0.11)]), $lowTaxRuleCollection),
                new PriceDefinition(0.102803738317757, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(15.99, 15.99, new CalculatedTaxCollection([new CalculatedTax(1.05, 7, 15.99)]), $lowTaxRuleCollection),
                new PriceDefinition(14.9439252336449, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(21.32, 21.32, new CalculatedTaxCollection([new CalculatedTax(1.39, 7, 21.32)]), $lowTaxRuleCollection),
                new PriceDefinition(19.9252336448598, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(50.00, 50.00, new CalculatedTaxCollection([new CalculatedTax(3.27, 7, 50.00)]), $lowTaxRuleCollection),
                new PriceDefinition(46.7289719626168, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(95799.97, 95799.97, new CalculatedTaxCollection([new CalculatedTax(6267.29, 7, 95799.97)]), $lowTaxRuleCollection),
                new PriceDefinition(89532.6822429906, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(0.05, 0.05, new CalculatedTaxCollection([new CalculatedTax(0.00, 7, 0.05)]), $lowTaxRuleCollection),
                new PriceDefinition(0.0467289719626168, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(0.01, 0.01, new CalculatedTaxCollection([new CalculatedTax(0.00, 7, 0.01)]), $lowTaxRuleCollection),
                new PriceDefinition(0.00934579439252336, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(0.08, 0.08, new CalculatedTaxCollection([new CalculatedTax(0.01, 7, 0.08)]), $lowTaxRuleCollection),
                new PriceDefinition(0.0747663551401869, $lowTaxRuleCollection),
            ], [
                new PriceRounding(2),
                new Price(-5.88, -5.88, new CalculatedTaxCollection([new CalculatedTax(-0.38, 7, -5.88)]), $lowTaxRuleCollection),
                new PriceDefinition(-5.49532710280374, $lowTaxRuleCollection),
            ], [
                new PriceRounding(3),
                new Price(15.999, 15.999, new CalculatedTaxCollection([new CalculatedTax(2.554, 19, 15.999)]), $highTaxRules),
                new PriceDefinition(13.4445378151261, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(21.322, 21.322, new CalculatedTaxCollection([new CalculatedTax(3.404, 19, 21.322)]), $highTaxRules),
                new PriceDefinition(17.9176470588235, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(50.00, 50.00, new CalculatedTaxCollection([new CalculatedTax(7.983, 19, 50.00)]), $highTaxRules),
                new PriceDefinition(42.01680672268908, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(95799.974, 95799.974, new CalculatedTaxCollection([new CalculatedTax(15295.794, 19, 95799.974)]), $highTaxRules),
                new PriceDefinition(80504.1798319328, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(0.005, 0.005, new CalculatedTaxCollection([new CalculatedTax(0.001, 19, 0.005)]), $highTaxRules),
                new PriceDefinition(0.00420168067226891, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(0.001, 0.001, new CalculatedTaxCollection([new CalculatedTax(0.000, 19, 0.001)]), $highTaxRules),
                new PriceDefinition(0.000840336134453782, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(0.008, 0.008, new CalculatedTaxCollection([new CalculatedTax(0.001, 19, 0.008)]), $highTaxRules),
                new PriceDefinition(0.00672268907563025, $highTaxRules),
            ], [
                new PriceRounding(3),
                new Price(-5.988, -5.988, new CalculatedTaxCollection([new CalculatedTax(-0.956, 19, -5.988)]), $highTaxRules),
                new PriceDefinition(-5.03193277310924, $highTaxRules),
            ],
        ];
    }
}
