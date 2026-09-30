<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceCollection::class)]
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

    public function testTotalPriceAmountSnapsFloatingPointResidualToZero(): void
    {
        // a voucher zeroing the cart must not leave a residual like -7.1E-15 (order matters)
        $collection = new PriceCollection([
            new CalculatedPrice(169, 169, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(-208.9, -208.9, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(39.9, 39.9, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        static::assertSame(0.0, $collection->getTotalPriceAmount());
        static::assertSame(0.0, $collection->sum()->getTotalPrice());
    }

    public function testUnitPriceAmountSnapsFloatingPointResidualToZero(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(169, 169, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(-208.9, -208.9, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(39.9, 39.9, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        static::assertSame(0.0, $collection->getUnitPriceAmount());
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

    public function testGetTaxRulesMergesTheRulesOfAllPrices(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection([new TaxRule(19)])),
            new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection([new TaxRule(7)])),
        ]);

        $rates = array_map(static fn (TaxRule $rule) => $rule->getTaxRate(), array_values($collection->getTaxRules()->getElements()));

        static::assertSame([19.0, 7.0], $rates);
    }

    public function testGetHighestTaxRuleReturnsTheHighestRateAtFullPercentage(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection([new TaxRule(7)])),
            new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection([new TaxRule(19, 50)])),
        ]);

        $highest = $collection->getHighestTaxRule();

        static::assertCount(1, $highest);
        $rule = $highest->first();
        static::assertInstanceOf(TaxRule::class, $rule);
        static::assertSame(19.0, $rule->getTaxRate());
        static::assertSame(100.0, $rule->getPercentage());
    }

    public function testGetHighestTaxRuleIsEmptyWithoutPrices(): void
    {
        static::assertCount(0, (new PriceCollection())->getHighestTaxRule());
    }

    public function testMergeCombinesTwoCollectionsIntoANewOne(): void
    {
        $collection = new PriceCollection([
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
        $other = new PriceCollection([
            new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);

        $merged = $collection->merge($other);

        static::assertCount(2, $merged);
        static::assertNotSame($collection, $merged);
        static::assertSame(30.0, $merged->sum()->getTotalPrice());
    }

    public function testApiAlias(): void
    {
        static::assertSame('cart_price_collection', (new PriceCollection())->getApiAlias());
    }
}
