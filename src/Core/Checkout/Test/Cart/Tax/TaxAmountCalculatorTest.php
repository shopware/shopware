<?php declare(strict_types=1);
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

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleCalculator;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxAmountCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\System\Touchpoint\TouchpointStruct;

class TaxAmountCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculationProvider
     *
     * @param string                  $calculationType
     * @param TaxDetector             $taxDetector
     * @param PriceCollection         $prices
     * @param CalculatedTaxCollection $expected
     */
    public function testCalculation(string $calculationType, TaxDetector $taxDetector, PriceCollection $prices, CalculatedTaxCollection $expected): void
    {
        $shop = $this->createMock(TouchpointStruct::class);
        $shop->method('getTaxCalculationType')->will(static::returnValue($calculationType));

        $context = $this->createMock(CheckoutContext::class);
        $context->method('getTouchpoint')->will(static::returnValue($shop));

        $taxAmountCalculator = new TaxAmountCalculator(
            new PercentageTaxRuleBuilder(),
            new TaxCalculator(
                new PriceRounding(2),
                [
                    new PercentageTaxRuleCalculator(new TaxRuleCalculator(new PriceRounding(2))),
                    new TaxRuleCalculator(new PriceRounding(2)),
                ]
            ),
            $taxDetector
        );

        static::assertEquals(
            $expected,
            $taxAmountCalculator->calculate($prices, $context)
        );
    }

    public function calculationProvider(): array
    {
        $grossPriceDetector = $this->createMock(TaxDetector::class);
        $grossPriceDetector->method('useGross')->will(static::returnValue(true));

        $netPriceDetector = $this->createMock(TaxDetector::class);
        $netPriceDetector->method('useGross')->will(static::returnValue(false));
        $netPriceDetector->method('isNetDelivery')->will(static::returnValue(false));

        $netDeliveryDetector = $this->createMock(TaxDetector::class);
        $netDeliveryDetector->method('useGross')->will(static::returnValue(false));
        $netDeliveryDetector->method('isNetDelivery')->will(static::returnValue(true));

        return [
            //0
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.27, 7, 4.32),
                ]),
            ],

            //1
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.78, 19, 4.83),
                ]),
            ],

            //2
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.52, 19, 3.22),
                    new CalculatedTax(0.09, 7, 1.44),
                ]),
            ],

            //3
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(3.03, 3.03, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 3.03)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new Price(
                        -2.30,
                        -2.30,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.56),
                            new CalculatedTax(-0.05, 7, -0.74),
                        ]),
                        new TaxRuleCollection([
                            new PercentageTaxRule(19, 0.677852348993289),
                            new PercentageTaxRule(7, 0.322147651006711),
                        ])
                    ),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.23, 19, 1.47),
                    new CalculatedTax(0.04, 7, 0.7),
                ]),
            ],

            //4
            [
                TaxAmountCalculator::CALCULATION_HORIZONTAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.28, 7, 4.32),
                ]),
            ],

            //5
            [
                TaxAmountCalculator::CALCULATION_HORIZONTAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.77, 19, 4.83),
                ]),
            ],

            //6
            [
                TaxAmountCalculator::CALCULATION_HORIZONTAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.51, 19, 3.22),
                    new CalculatedTax(0.09, 7, 1.44),
                ]),
            ],

            //7
            [
                TaxAmountCalculator::CALCULATION_HORIZONTAL,
                $grossPriceDetector,
                new PriceCollection([
                    new Price(3.03, 3.03, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 3.03)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new Price(
                        -2.30,
                        -2.30,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.56),
                            new CalculatedTax(-0.05, 7, -0.74),
                        ]),
                        new TaxRuleCollection([
                            new PercentageTaxRule(19, 0.677852348993289),
                            new PercentageTaxRule(7, 0.322147651006711),
                        ])
                    ),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.23, 19, 1.47),
                    new CalculatedTax(0.05, 7, 0.7),
                ]),
            ],

            //8
            [
                TaxAmountCalculator::CALCULATION_HORIZONTAL,
                $netDeliveryDetector,
                new PriceCollection([
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([]),
            ],
            //9
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netDeliveryDetector,
                new PriceCollection([
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([]),
            ],

            //net price calculation - vertical
            //10
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netPriceDetector,
                new PriceCollection([
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.27, 7, 4.05),
                ]),
            ],

            //11
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netPriceDetector,
                new PriceCollection([
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.78, 19, 4.05),
                ]),
            ],

            //12
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netPriceDetector,
                new PriceCollection([
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.52, 19, 2.70),
                    new CalculatedTax(0.09, 7, 1.35),
                ]),
            ],

            //13
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netPriceDetector,
                new PriceCollection([
                    new Price(2.55, 2.55, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 2.55)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new Price(
                        -2.0,
                        -2.0,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.31),
                            new CalculatedTax(-0.05, 7, -0.69),
                        ]),
                        new TaxRuleCollection([
                            new PercentageTaxRule(19, 0.653846153846154),
                            new PercentageTaxRule(7, 0.346153846153846),
                        ])
                    ),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.23, 19, 1.24),
                    new CalculatedTax(0.04, 7, 0.66),
                ]),
            ],

            //14
            [
                TaxAmountCalculator::CALCULATION_HORIZONTAL,
                $netPriceDetector,
                new PriceCollection([
                    new Price(2.55, 2.55, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 2.55)]), new TaxRuleCollection([new TaxRule(19)])),
                    new Price(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new Price(
                        -2.0,
                        -2.0,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.31),
                            new CalculatedTax(-0.05, 7, -0.69),
                        ]),
                        new TaxRuleCollection([
                            new PercentageTaxRule(19, 0.653846153846154),
                            new PercentageTaxRule(7, 0.346153846153846),
                        ])
                    ),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(0.24, 19, 1.24),
                    new CalculatedTax(0.05, 7, 0.66),
                ]),
            ],
        ];
    }
}
