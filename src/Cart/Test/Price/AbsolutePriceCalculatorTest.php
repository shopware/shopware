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

namespace Shopware\Cart\Test\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Price\AbsolutePriceCalculator;
use Shopware\Cart\Price\GrossPriceCalculator;
use Shopware\Cart\Price\NetPriceCalculator;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceRounding;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Cart\Price\Struct\DerivedCalculatedPrice;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Cart\Tax\PercentageTaxRuleCalculator;
use Shopware\Cart\Tax\Struct\CalculatedTax;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\TaxRuleCalculator;
use Shopware\Cart\Test\Common\Generator;

class AbsolutePriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculateAbsolutePriceOfGrossPricesProvider
     *
     * @param float                     $price
     * @param DerivedCalculatedPrice    $expected
     * @param CalculatedPriceCollection $prices
     */
    public function testCalculateAbsolutePriceOfGrossPrices(
        float $price,
        DerivedCalculatedPrice $expected,
        CalculatedPriceCollection $prices
    ): void {
        $rounding = new PriceRounding(2);

        $taxCalculator = new TaxCalculator(
            new PriceRounding(2),
            [
                new TaxRuleCalculator($rounding),
                new PercentageTaxRuleCalculator(new TaxRuleCalculator($rounding)),
            ]
        );

        $calculator = new AbsolutePriceCalculator(
            new PriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding),
                new NetPriceCalculator($taxCalculator, $rounding),
                Generator::createGrossPriceDetector()
            ),
            new PercentageTaxRuleBuilder()
        );

        $calculatedPrice = $calculator->calculate(
            $price,
            $prices,
            Generator::createContext()
        );
        static::assertEquals($expected, $calculatedPrice);
        static::assertEquals($expected->getCalculatedTaxes(), $calculatedPrice->getCalculatedTaxes());
        static::assertEquals($expected->getTaxRules(), $calculatedPrice->getTaxRules());
        static::assertEquals($expected->getTotalPrice(), $calculatedPrice->getTotalPrice());
        static::assertEquals($expected->getUnitPrice(), $calculatedPrice->getUnitPrice());
        static::assertEquals($expected->getQuantity(), $calculatedPrice->getQuantity());
        static::assertEquals($expected->getCalculationBasePrices(), $calculatedPrice->getCalculationBasePrices());
    }

    public function calculateAbsolutePriceOfGrossPricesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);

        $taxRules = new TaxRuleCollection([
            new PercentageTaxRule(19, 50),
            new PercentageTaxRule(7, 50),
        ]);

        //prices of cart line items
        $prices = new CalculatedPriceCollection([
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(4.79, 19, 30.00)]), $highTax),
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(1.96, 7, 30.00)]), $highTax),
        ]);

        return [
            [
                -6,
                //expected calculated "discount" price
                new DerivedCalculatedPrice(
                    -6,
                    -6,
                    new CalculatedTaxCollection([
                        new CalculatedTax(-0.48, 19, -3),
                        new CalculatedTax(-0.20, 7, -3),
                    ]),
                    $taxRules,
                    1,
                    $prices
                ),
                $prices,
            ],
        ];
    }
}
