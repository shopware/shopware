<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

/**
 * @internal
 */
#[CoversClass(CalculatedTaxCollection::class)]
class CalculatedTaxCollectionTest extends TestCase
{
    final public const DUMMY_TAX_NAME = 'dummy-tax';

    public function testCollectionIsCountable(): void
    {
        $collection = new CalculatedTaxCollection();
        static::assertCount(0, $collection);
    }

    public function testCountReturnsCorrectValue(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(10.99, 19, 1),
            new CalculatedTax(5.99, 14, 1),
            new CalculatedTax(1.99, 2, 1),
        ]);
        static::assertCount(3, $collection);
    }

    public function testAddFunctionAddsATax(): void
    {
        $collection = new CalculatedTaxCollection();
        $collection->add(
            new CalculatedTax(10.99, 19, 1)
        );

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(10.99, 19, 1),
            ]),
            $collection
        );
    }

    public function testTaxesCanBeGetterByTheirRate(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(4.40, 18, 1),
            new CalculatedTax(3.30, 17, 1),
        ]);
        static::assertEquals(
            new CalculatedTax(5.50, 19, 1),
            $collection->get('19')
        );
    }

    public function testTaxAmountCanBeSummed(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(4.40, 18, 1),
            new CalculatedTax(3.30, 17, 1),
        ]);
        static::assertSame(13.2, $collection->getAmount());
    }

    public function testIncrementFunctionAddsNewCalculatedTaxIfNotExist(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
        ]);

        $collection->merge(
            new CalculatedTaxCollection([new CalculatedTax(5.50, 18, 1)])
        );

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(5.50, 19, 1),
                new CalculatedTax(5.50, 18, 1),
            ]),
            $collection
        );
    }

    public function testIncrementFunctionIncrementsExistingTaxes(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
        ]);
        $collection->merge(new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
        ]));

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(11.00, 19, 2),
            ]),
            $collection
        );
    }

    public function testIncrementFunctionIncrementExistingTaxAmounts(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(5.50, 18, 1),
            new CalculatedTax(5.50, 17, 1),
        ]);

        $collection->merge(new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(5.50, 18, 1),
            new CalculatedTax(5.50, 17, 1),
        ]));

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(11.00, 19, 2),
                new CalculatedTax(11.00, 18, 2),
                new CalculatedTax(11.00, 17, 2),
            ]),
            $collection
        );
    }

    public function testIncrementFunctionWorksWithEmptyCollection(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(5.50, 18, 1),
            new CalculatedTax(5.50, 17, 1),
        ]);
        $collection->merge(new CalculatedTaxCollection());

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(5.50, 19, 1),
                new CalculatedTax(5.50, 18, 1),
                new CalculatedTax(5.50, 17, 1),
            ]),
            $collection
        );
    }

    public function testTaxesCanBeRemovedByRate(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(5.50, 18, 1),
            new CalculatedTax(5.50, 17, 1),
        ]);
        $collection->remove(19);

        static::assertEquals(new CalculatedTaxCollection([
            new CalculatedTax(5.50, 18, 1),
            new CalculatedTax(5.50, 17, 1),
        ]), $collection);
    }

    public function testClearFunctionRemovesAllTaxes(): void
    {
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            new CalculatedTax(5.50, 18, 1),
            new CalculatedTax(5.50, 17, 1),
        ]);

        $collection->clear();
        static::assertEquals(new CalculatedTaxCollection(), $collection);
    }

    public function testGetOnEmptyCollection(): void
    {
        $collection = new CalculatedTaxCollection();
        static::assertNull($collection->get('19'));
    }

    public function testRemoveElement(): void
    {
        $toRemove = new CalculatedTax(5.50, 18, 1);
        $collection = new CalculatedTaxCollection([
            new CalculatedTax(5.50, 19, 1),
            $toRemove,
            new CalculatedTax(5.50, 17, 1),
        ]);

        $collection->removeElement($toRemove);

        static::assertEquals(
            new CalculatedTaxCollection([
                new CalculatedTax(5.50, 19, 1),
                new CalculatedTax(5.50, 17, 1),
            ]),
            $collection
        );
    }
}
