<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class TaxRuleCollectionTest extends TestCase
{
    public function testCollectionIsCountable(): void
    {
        $collection = new TaxRuleCollection();
        static::assertCount(0, $collection);
    }

    public function testCountReturnsCorrectValue(): void
    {
        $collection = new TaxRuleCollection([
            new TaxRule(19),
            new TaxRule(18),
            new TaxRule(17),
        ]);
        static::assertCount(3, $collection);
    }

    public function testTaxRateIsUsedAsUniqueIdentifier(): void
    {
        $collection = new TaxRuleCollection([
            new TaxRule(19),
            new TaxRule(19),
            new TaxRule(19),
        ]);

        static::assertEquals(
            new TaxRuleCollection([new TaxRule(19)]),
            $collection
        );
    }

    public function testElementCanBeAccessedByTaxRate(): void
    {
        $collection = new TaxRuleCollection([
            new TaxRule(19),
            new TaxRule(18),
            new TaxRule(17),
        ]);
        static::assertEquals(
            new TaxRule(19),
            $collection->get(19)
        );
    }

    public function testTaxRateCanBeAddedToCollection(): void
    {
        $collection = new TaxRuleCollection();
        $collection->add(new TaxRule(19));

        static::assertEquals(
            new TaxRuleCollection([new TaxRule(19)]),
            $collection
        );
    }

    public function testCollectionCanBeCleared(): void
    {
        $collection = new TaxRuleCollection([
            new TaxRule(19),
            new TaxRule(18),
            new TaxRule(17),
        ]);
        $collection->clear();

        static::assertEquals(new TaxRuleCollection(), $collection);
    }

    public function testMergeFunctionReturnsNewInstance(): void
    {
        $a = new TaxRuleCollection([new TaxRule(19)]);
        $b = new TaxRuleCollection([new TaxRule(18)]);
        $c = $a->merge($b);

        static::assertNotSame($c, $a);
        static::assertNotSame($c, $b);
    }

    public function testMergeFunctionMergesAllTaxRules(): void
    {
        $a = new TaxRuleCollection([new TaxRule(19)]);
        $b = new TaxRuleCollection([new TaxRule(18)]);
        $c = $a->merge($b);

        static::assertEquals(
            new TaxRuleCollection([
                new TaxRule(19),
                new TaxRule(18),
            ]),
            $c
        );
    }

    public function testTaxRuleCanBeRemovedByRate(): void
    {
        $collection = new TaxRuleCollection([
            new TaxRule(19),
            new TaxRule(18),
            new TaxRule(17),
        ]);
        $collection->remove(19);
        static::assertEquals(
            new TaxRuleCollection([
                new TaxRule(18),
                new TaxRule(17),
            ]),
            $collection
        );
    }

    public function testGetOnEmptyCollection(): void
    {
        $collection = new TaxRuleCollection([]);
        static::assertNull($collection->get(19));
    }

    public function testRemoveElement(): void
    {
        $toRemove = new TaxRule(18);

        $collection = new TaxRuleCollection([
            new TaxRule(19),
            $toRemove,
            new TaxRule(17),
        ]);

        $collection->removeElement($toRemove);

        static::assertEquals(
            new TaxRuleCollection([
                new TaxRule(19),
                new TaxRule(17),
            ]),
            $collection
        );
    }
}
