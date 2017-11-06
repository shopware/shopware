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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Domain\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Price\Struct\PriceCollection;
use Shopware\Cart\Tax\Struct\CalculatedTax;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;

class PriceCollectionTest extends TestCase
{
    public function testCollectionIsCountable(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection();
        static::assertCount(0, $collection);
    }

    public function testCountReturnsCorrectValue(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection([
            new \Shopware\Cart\Price\Struct\Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new \Shopware\Cart\Price\Struct\Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
        static::assertCount(3, $collection);
    }

    public function testAddFunctionAddsAPrice(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection();
        $collection->add(new Price(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));

        static::assertEquals(
            new \Shopware\Cart\Price\Struct\PriceCollection([
                new \Shopware\Cart\Price\Struct\Price(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $collection
        );
    }

    public function testFillFunctionFillsTheCollection(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection();
        $collection->fill([
            new Price(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new \Shopware\Cart\Price\Struct\Price(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        static::assertEquals(
            new \Shopware\Cart\Price\Struct\PriceCollection([
                new \Shopware\Cart\Price\Struct\Price(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new Price(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $collection
        );
    }

    public function testTotalAmountWithEmptyCollection(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection();
        static::assertSame(0.0, $collection->sum()->getTotalPrice());
    }

    public function testTotalAmountWithMultiplePrices(): void
    {
        $collection = new PriceCollection([
            new Price(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new Price(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
        static::assertSame(500.0, $collection->sum()->getTotalPrice());
    }

    public function testGetTaxesReturnsACalculatedTaxCollection(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection();
        static::assertEquals(new CalculatedTaxCollection(), $collection->getCalculatedTaxes());
    }

    public function testGetTaxesReturnsCollectionWithAllTaxes(): void
    {
        $collection = new PriceCollection([
            new Price(
                200,
                200,
                new CalculatedTaxCollection([
                    new CalculatedTax(1, 15, 1),
                    new CalculatedTax(2, 16, 1),
                    new CalculatedTax(3, 17, 1),
                ]),
                new TaxRuleCollection()
            ),
            new \Shopware\Cart\Price\Struct\Price(
                300,
                300,
                new CalculatedTaxCollection([
                    new CalculatedTax(4, 19, 1),
                    new CalculatedTax(5, 20, 1),
                    new CalculatedTax(6, 21, 1),
                ]),
                new TaxRuleCollection()
            ),
        ]);

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(1, 15, 1),
                new CalculatedTax(2, 16, 1),
                new CalculatedTax(3, 17, 1),
                new CalculatedTax(4, 19, 1),
                new CalculatedTax(5, 20, 1),
                new CalculatedTax(6, 21, 1),
            ]),
            $collection->getCalculatedTaxes()
        );
    }

    public function testClearFunctionRemovesAllPrices(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection([
            new \Shopware\Cart\Price\Struct\Price(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new Price(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        $collection->clear();
        static::assertEquals(new \Shopware\Cart\Price\Struct\PriceCollection(), $collection);
    }

    public function testGet(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection([
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new \Shopware\Cart\Price\Struct\Price(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        $this->assertEquals(
            new \Shopware\Cart\Price\Struct\Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(0)
        );

        $this->assertEquals(
            new \Shopware\Cart\Price\Struct\Price(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(1)
        );
        $this->assertNull($collection->get(2));
    }

    public function testRemove(): void
    {
        $collection = new \Shopware\Cart\Price\Struct\PriceCollection([
            new \Shopware\Cart\Price\Struct\Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new Price(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        $this->assertEquals(
            new \Shopware\Cart\Price\Struct\Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(0)
        );

        $this->assertEquals(
            new Price(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(1)
        );

        $collection->remove(0);
        $collection->remove(1);
        $this->assertNull($collection->get(0));
        $this->assertNull($collection->get(1));
    }
}
