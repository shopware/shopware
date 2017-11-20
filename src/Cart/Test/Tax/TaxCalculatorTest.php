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

namespace Shopware\Cart\Test\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Exception\TaxRuleNotSupportedException;
use Shopware\Cart\Price\PriceRounding;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Cart\Tax\Struct\TaxRuleInterface;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\TaxRuleCalculator;

class TaxCalculatorTest extends TestCase
{
    /**
     * @dataProvider netPricesToGross
     *
     * @param float            $expected
     * @param PriceRounding    $rounding
     * @param TaxRuleInterface $taxRule
     * @param float            $net
     */
    public function testCalculateGrossPriceOfNetPrice($expected, PriceRounding $rounding, TaxRuleInterface $taxRule, $net): void
    {
        $calculator = new TaxCalculator(
            $rounding,
            [new TaxRuleCalculator($rounding)]
        );

        $rules = new TaxRuleCollection([$taxRule]);

        static::assertSame(
            $expected,
            $calculator->calculateGross($net, $rules)
        );
    }

    public function testNotSupportedRule(): void
    {
        $calculator = new TaxCalculator(
            new PriceRounding(2),
            []
        );

        $rule = new TaxRule(19);
        $rules = new TaxRuleCollection([$rule]);

        try {
            $calculator->calculateGross(1, $rules);
        } catch (\Exception $e) {
            $this->assertInstanceOf(TaxRuleNotSupportedException::class, $e);

            /* @var TaxRuleNotSupportedException $e */
            $this->assertSame($rule, $e->getTaxRule());
        }
    }

    /**
     * @return array
     */
    public function netPricesToGross(): array
    {
        return [
            [0.01,         new PriceRounding(2), new TaxRule(7),  0.00934579439252336],
            [0.08,         new PriceRounding(2), new TaxRule(7),  0.0747663551401869],
            [5.00,         new PriceRounding(2), new TaxRule(7),  4.67289719626168],
            [299999.99,    new PriceRounding(2), new TaxRule(7),  280373.822429907],
            [13.76,        new PriceRounding(2), new TaxRule(7),  12.8598130841121],
            [0.001,        new PriceRounding(3), new TaxRule(7),  0.000934579439252336],
            [0.008,        new PriceRounding(3), new TaxRule(7),  0.00747663551401869],
            [5.00,         new PriceRounding(3), new TaxRule(7),  4.67289719626168],
            [299999.999,   new PriceRounding(3), new TaxRule(7),  280373.830841121],
            [13.767,       new PriceRounding(3), new TaxRule(7),  12.8663551401869],
            [0.0001,       new PriceRounding(4), new TaxRule(7),  0.0000934579439252336],
            [0.0008,       new PriceRounding(4), new TaxRule(7),  0.000747663551401869],
            [5.00,         new PriceRounding(4), new TaxRule(7),  4.67289719626168],
            [299999.9999,  new PriceRounding(4), new TaxRule(7),  280373.831682243],
            [13.7676,      new PriceRounding(4), new TaxRule(7),  12.8669158878505],
            [0.01,         new PriceRounding(2), new TaxRule(19), 0.00840336134453782],
            [0.08,         new PriceRounding(2), new TaxRule(19), 0.0672268907563025],
            [5.00,         new PriceRounding(2), new TaxRule(19), 4.20168067226891],
            [299999.99,    new PriceRounding(2), new TaxRule(19), 252100.831932773],
            [13.76,        new PriceRounding(2), new TaxRule(19), 11.563025210084],
            [0.001,        new PriceRounding(3), new TaxRule(19), 0.000840336134453782],
            [0.008,        new PriceRounding(3), new TaxRule(19), 0.00672268907563025],
            [5.00,         new PriceRounding(3), new TaxRule(19), 4.20168067226891],
            [299999.999,   new PriceRounding(3), new TaxRule(19), 252100.839495798],
            [13.767,       new PriceRounding(3), new TaxRule(19), 11.5689075630252],
            [0.0001,       new PriceRounding(4), new TaxRule(19), 0.0000840336134453782],
            [0.0008,       new PriceRounding(4), new TaxRule(19), 0.000672268907563025],
            [5.00,         new PriceRounding(4), new TaxRule(19), 4.20168067226891],
            [299999.9999,  new PriceRounding(4), new TaxRule(19), 252100.840252101],
            [13.7676,      new PriceRounding(4), new TaxRule(19), 11.5694117647059],
        ];
    }
}
