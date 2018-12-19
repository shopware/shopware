<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class PriceCollectionTest extends TestCase
{
    public function testCollectionIsCountable(): void
    {
        $collection = new PriceCollection();
        static::assertCount(0, $collection);
    }

    public function testCountReturnsCorrectValue(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
        static::assertCount(3, $collection);
    }

    public function testAddFunctionAddsAPrice(): void
    {
        $collection = new PriceCollection();
        $collection->add(new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()));

        static::assertEquals(
            new PriceCollection([
                new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $collection
        );
    }

    public function testTotalAmountWithEmptyCollection(): void
    {
        $collection = new PriceCollection();
        static::assertSame(0.0, $collection->sum()->getTotalPrice());
    }

    public function testTotalAmountWithMultiplePrices(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
        static::assertSame(500.0, $collection->sum()->getTotalPrice());
    }

    public function testGetTaxesReturnsACalculatedTaxCollection(): void
    {
        $collection = new PriceCollection();
        static::assertEquals(new CalculatedTaxCollection(), $collection->getCalculatedTaxes());
    }

    public function testGetTaxesReturnsCollectionWithAllTaxes(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(
                200,
                200,
                new CalculatedTaxCollection([
                    new CalculatedTax(1, 15, 1),
                    new CalculatedTax(2, 16, 1),
                    new CalculatedTax(3, 17, 1),
                ]),
                new TaxRuleCollection()
            ),
            new CalculatedPrice(
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
        $collection = new PriceCollection([
            new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        $collection->clear();
        static::assertEquals(new PriceCollection(), $collection);
    }

    public function testGet(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        static::assertEquals(
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(0)
        );

        static::assertEquals(
            new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(1)
        );
        static::assertNull($collection->get(2));
    }

    public function testRemove(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        static::assertEquals(
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(0)
        );

        static::assertEquals(
            new CalculatedPrice(2, 2, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $collection->get(1)
        );

        $collection->remove(0);
        $collection->remove(1);
        static::assertNull($collection->get(0));
        static::assertNull($collection->get(1));
    }
}
