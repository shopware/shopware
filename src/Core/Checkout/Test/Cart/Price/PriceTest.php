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

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class PriceTest extends TestCase
{
    /**
     * @dataProvider addCases
     *
     * @param Price $a
     * @param Price $b
     * @param Price $expected
     */
    public function testAdd(Price $a, Price $b, Price $expected): void
    {
        $a->add($b);
        $this->assertEquals($expected->getQuantity(), $a->getQuantity());
        $this->assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        $this->assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        $this->assertEquals($expected->getTotalPrice(), $a->getTotalPrice());
        $this->assertEquals($expected->getTaxRules(), $a->getTaxRules());
        $this->assertEquals($expected->getCalculatedTaxes(), $a->getCalculatedTaxes());
        $this->assertEquals($expected, $a);
    }

    /**
     * @dataProvider subCases
     *
     * @param Price $a
     * @param Price $b
     * @param Price $expected
     */
    public function testSub(Price $a, Price $b, Price $expected): void
    {
        $a->sub($b);
        $this->assertEquals($expected->getQuantity(), $a->getQuantity());
        $this->assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        $this->assertEquals($expected->getUnitPrice(), $a->getUnitPrice());
        $this->assertEquals($expected->getTotalPrice(), $a->getTotalPrice());
        $this->assertEquals($expected->getTaxRules(), $a->getTaxRules());
        $this->assertEquals($expected->getCalculatedTaxes(), $a->getCalculatedTaxes());
        $this->assertEquals($expected, $a);
    }

    public function addCases(): array
    {
        return [
            [
                new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new Price(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ],
            [
                new Price(1, 1, new CalculatedTaxCollection([new CalculatedTax(0.55, 19, 1)]), new TaxRuleCollection()),
                new Price(1, 1, new CalculatedTaxCollection([new CalculatedTax(0.55, 19, 1)]), new TaxRuleCollection()),
                new Price(2, 2, new CalculatedTaxCollection([new CalculatedTax(1.10, 19, 2)]), new TaxRuleCollection()),
            ],
            [
                new Price(1, 1, new CalculatedTaxCollection([new CalculatedTax(0.55, 19, 1)]), new TaxRuleCollection()),
                new Price(-0.5, -0.5, new CalculatedTaxCollection([new CalculatedTax(-0.5, 19, -0.5)]), new TaxRuleCollection()),
                new Price(0.5, 0.5, new CalculatedTaxCollection([new CalculatedTax(0.05, 19, 0.5)]), new TaxRuleCollection()),
            ],
        ];
    }

    public function subCases(): array
    {
        return [
            [
                new Price(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ],
        ];
    }
}
