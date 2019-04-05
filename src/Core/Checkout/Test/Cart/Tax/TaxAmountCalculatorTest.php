<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxAmountCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class TaxAmountCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculationProvider
     */
    public function testCalculation(string $calculationType, TaxDetector $taxDetector, PriceCollection $prices, CalculatedTaxCollection $expected): void
    {
        $shop = $this->createMock(SalesChannelEntity::class);
        $shop->method('getTaxCalculationType')->willReturn($calculationType);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($shop);

        $context->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $taxAmountCalculator = new TaxAmountCalculator(
            new PercentageTaxRuleBuilder(),
            new TaxCalculator(
                new PriceRounding(),
                new TaxRuleCalculator(new PriceRounding())
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
        $grossPriceDetector->method('useGross')->willReturn(true);

        $netPriceDetector = $this->createMock(TaxDetector::class);
        $netPriceDetector->method('useGross')->willReturn(false);
        $netPriceDetector->method('isNetDelivery')->willReturn(false);

        $netDeliveryDetector = $this->createMock(TaxDetector::class);
        $netDeliveryDetector->method('useGross')->willReturn(false);
        $netDeliveryDetector->method('isNetDelivery')->willReturn(true);

        return [
            //0
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $grossPriceDetector,
                new PriceCollection([
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
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
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
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
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
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
                    new CalculatedPrice(3.03, 3.03, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 3.03)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new CalculatedPrice(
                        -2.30,
                        -2.30,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.56),
                            new CalculatedTax(-0.05, 7, -0.74),
                        ]),
                        new TaxRuleCollection([
                            new TaxRule(19, 0.677852348993289),
                            new TaxRule(7, 0.322147651006711),
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
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
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
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
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
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.61, 1.61, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.61)]), new TaxRuleCollection([new TaxRule(19)])),
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
                    new CalculatedPrice(3.03, 3.03, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 3.03)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new CalculatedPrice(
                        -2.30,
                        -2.30,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.56),
                            new CalculatedTax(-0.05, 7, -0.74),
                        ]),
                        new TaxRuleCollection([
                            new TaxRule(19, 0.677852348993289),
                            new TaxRule(7, 0.322147651006711),
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
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([]),
            ],
            //9
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netDeliveryDetector,
                new PriceCollection([
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.44, 1.44, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.44)]), new TaxRuleCollection([new TaxRule(7)])),
                ]),
                new CalculatedTaxCollection([]),
            ],

            //net price calculation - vertical
            //10
            [
                TaxAmountCalculator::CALCULATION_VERTICAL,
                $netPriceDetector,
                new PriceCollection([
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
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
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
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
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(7)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.26, 19, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),
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
                    new CalculatedPrice(2.55, 2.55, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 2.55)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new CalculatedPrice(
                        -2.0,
                        -2.0,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.31),
                            new CalculatedTax(-0.05, 7, -0.69),
                        ]),
                        new TaxRuleCollection([
                            new TaxRule(19, 0.653846153846154),
                            new TaxRule(7, 0.346153846153846),
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
                    new CalculatedPrice(2.55, 2.55, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 2.55)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.35, 1.35, new CalculatedTaxCollection([new CalculatedTax(0.09, 7, 1.35)]), new TaxRuleCollection([new TaxRule(19)])),

                    //percentage voucher
                    new CalculatedPrice(
                        -2.0,
                        -2.0,
                        new CalculatedTaxCollection([
                            new CalculatedTax(-0.25, 19, -1.31),
                            new CalculatedTax(-0.05, 7, -0.69),
                        ]),
                        new TaxRuleCollection([
                            new TaxRule(19, 0.653846153846154),
                            new TaxRule(7, 0.346153846153846),
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
