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
use Shopware\Cart\Price\GrossPriceCalculator;
use Shopware\Cart\Price\NetPriceCalculator;
use Shopware\Cart\Price\PercentagePriceCalculator;
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

class PercentagePriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculatePercentagePriceOfGrossPricesProvider
     *
     * @param float                     $percentage
     * @param DerivedCalculatedPrice    $expected
     * @param CalculatedPriceCollection $prices
     */
    public function testCalculatePercentagePriceOfGrossPrices(
        $percentage,
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

        $calculator = new PercentagePriceCalculator(
            new PriceRounding(2),
            new PriceCalculator(
                new GrossPriceCalculator($taxCalculator, new PriceRounding(2)),
                new NetPriceCalculator($taxCalculator, new PriceRounding(2)),
                Generator::createGrossPriceDetector()
            ),
            new PercentageTaxRuleBuilder()
        );

        $price = $calculator->calculate(
            $percentage,
            $prices,
            Generator::createContext()
        );
        static::assertEquals($expected, $price);
        static::assertEquals($expected->getCalculatedTaxes(), $price->getCalculatedTaxes());
        static::assertEquals($expected->getTaxRules(), $price->getTaxRules());
        static::assertEquals($expected->getTotalPrice(), $price->getTotalPrice());
        static::assertEquals($expected->getUnitPrice(), $price->getUnitPrice());
        static::assertEquals($expected->getQuantity(), $price->getQuantity());
    }

    public function calculatePercentagePriceOfGrossPricesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);
        //prices of cart line items
        $prices = new CalculatedPriceCollection([
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(4.79, 19, 30.00)]), $highTax),
            new CalculatedPrice(30.00, 30.00, new CalculatedTaxCollection([new CalculatedTax(1.96, 7, 30.00)]), $highTax),
        ]);

        return [
            [
                //10% discount
                -10,
                //expected calculated "discount" price
                new DerivedCalculatedPrice(
                    -6.0,
                    -6.0,
                    new CalculatedTaxCollection([
                        new CalculatedTax(-0.48, 19, -3.0),
                        new CalculatedTax(-0.20, 7, -3.0),
                    ]),
                    new TaxRuleCollection([
                        new PercentageTaxRule(19, 50),
                        new PercentageTaxRule(7, 50),
                    ]),
                    1,
                    $prices
                ),
                $prices,
            ],
        ];
    }
}
