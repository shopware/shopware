<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class LineItemCollectionTest extends TestCase
{
    public function testCollectionIsCountable(): void
    {
        $collection = new LineItemCollection();
        static::assertCount(0, $collection);
    }

    public function testCountReturnsCorrectValue(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', '', 1),
            new LineItem('B', '', 1),
            new LineItem('C', '', 1),
        ]);
        static::assertCount(3, $collection);
    }

    public function testCollectionStacksSameIdentifier(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'a', 1))->setStackable(true),
            (new LineItem('A', 'a', 2))->setStackable(true),
            (new LineItem('A', 'a', 3))->setStackable(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'a', 6))->setStackable(true),
            ]),
            $collection
        );
    }

    public function testFilterReturnsNewCollectionWithCorrectItems(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A1', 'A', 1),
            new LineItem('A2', 'A', 1),
            new LineItem('B', 'B', 1),
            new LineItem('B2', 'B', 1),
            new LineItem('B3', 'B', 1),
            new LineItem('B4', 'B', 1),
            new LineItem('C', 'C', 1),
        ]);

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A1', 'A', 1),
                new LineItem('A2', 'A', 1),
            ]),
            $collection->filterType('A')
        );
        static::assertEquals(
            new LineItemCollection([
                new LineItem('B', 'B', 1),
                new LineItem('B2', 'B', 1),
                new LineItem('B3', 'B', 1),
                new LineItem('B4', 'B', 1),
            ]),
            $collection->filterType('B')
        );
        static::assertEquals(
            new LineItemCollection([
                new LineItem('C', 'C', 1),
            ]),
            $collection->filterType('C')
        );

        static::assertEquals(
            new LineItemCollection(),
            $collection->filterType('NOT EXISTS')
        );
    }

    public function testFilterReturnsCollection(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]);

        static::assertInstanceOf(LineItemCollection::class, $collection->filterType('a'));
    }

    public function testFilterReturnsNewCollection(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]);

        static::assertNotSame($collection, $collection->filterType('a'));
    }

    public function testLineItemsCanBeCleared(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]);
        $collection->clear();
        static::assertEquals(new LineItemCollection(), $collection);
    }

    public function testLineItemsCanBeRemovedByIdentifier(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]);
        $collection->remove('A');

        static::assertEquals(new LineItemCollection([
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]), $collection);
    }

    public function testIdentifiersCanEasyAccessed(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]);

        static::assertSame([
            'A', 'B', 'C',
        ], $collection->getKeys());
    }

    public function testGetOnEmptyCollection(): void
    {
        $collection = new LineItemCollection();
        static::assertNull($collection->get('not found'));
    }

    public function testRemoveElement(): void
    {
        $first = new LineItem('A', 'temp', 1);

        $collection = new LineItemCollection([
            $first,
            new LineItem('B', 'temp', 1),
        ]);

        $collection->removeElement($first);

        static::assertEquals(
            new LineItemCollection([new LineItem('B', 'temp', 1)]),
            $collection
        );
    }

    public function testExists(): void
    {
        $first = new LineItem('A', 'temp', 1);
        $second = new LineItem('B2', 'temp', 1);

        $collection = new LineItemCollection([
            $first,
            new LineItem('B', 'temp', 1),
        ]);

        static::assertTrue($collection->exists($first));
        static::assertFalse($collection->exists($second));
    }

    public function testGetCollectivePayload(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'temp', 1))->setPayload(['foo' => 'bar']),
            (new LineItem('B', 'temp', 1))->setPayload(['bar' => 'foo']),
        ]);

        static::assertEquals(
            [
                'A' => ['foo' => 'bar'],
                'B' => ['bar' => 'foo'],
            ],
            $collection->getPayload()
        );
    }

    public function testCollectionSumsQuantityOfSameKey(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test'))->setStackable(true),
            (new LineItem('A', 'test', 2))->setStackable(true),
            (new LineItem('A', 'test', 3))->setStackable(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', 6))->setStackable(true),
            ]),
            $collection
        );
    }

    public function testCartThrowsExceptionOnLineItemCollision(): void
    {
        $cart = new Cart('test', 'test');

        $cart->add(new LineItem('a', 'first-type'));

        $this->expectException(MixedLineItemTypeException::class);
        $cart->add(new LineItem('a', 'other-type'));
    }

    public function testGetLineItemByIdentifier(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'test', 3),
            new LineItem('B', 'test', 3),
            new LineItem('C', 'test', 3),
            new LineItem('D', 'test', 3),
        ]);

        static::assertEquals(
            new LineItem('C', 'test', 3),
            $collection->get('C')
        );
    }

    public function testFilterGoodsReturnsOnlyGoods(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', 3))->setGood(true),
            (new LineItem('B', 'test', 3))->setGood(false),
            (new LineItem('C', 'test', 3))->setGood(false),
            (new LineItem('D', 'test', 3))->setGood(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', 3))->setGood(true),
                (new LineItem('D', 'test', 3))->setGood(true),
            ]),
            $collection->filterGoods()
        );
    }

    public function testFilterGoodsReturnsNewCollection(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', 3))->setGood(true),
            (new LineItem('B', 'test', 3))->setGood(true),
            (new LineItem('C', 'test', 3))->setGood(true),
            (new LineItem('D', 'test', 3))->setGood(true),
        ]);

        static::assertNotSame(
            $collection->filterGoods(),
            $collection->filterGoods()
        );
    }

    public function testGetPricesCollectionOfMultipleItems(): void
    {
        $lineItems = new LineItemCollection([
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection())),

            (new LineItem('B', 'test'))
                ->setPrice(new CalculatedPrice(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection())),
        ]);

        static::assertEquals(
            new PriceCollection([
                new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new CalculatedPrice(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $lineItems->getPrices()
        );
    }

    public function testRemoveWithNoneExistingIdentifier(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'test', 3),
            new LineItem('B', 'test', 3),
            new LineItem('C', 'test', 3),
            new LineItem('D', 'test', 3),
        ]);

        $collection->remove('X');

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A', 'test', 3),
                new LineItem('B', 'test', 3),
                new LineItem('C', 'test', 3),
                new LineItem('D', 'test', 3),
            ]),
            $collection
        );
    }

    public function testRemoveWithNotExisting(): void
    {
        $c = new LineItem('C', 'test', 3);

        $collection = new LineItemCollection([
            new LineItem('A', 'test', 3),
            new LineItem('B', 'test', 3),
            new LineItem('D', 'test', 3),
        ]);

        $collection->removeElement($c);

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A', 'test', 3),
                new LineItem('B', 'test', 3),
                new LineItem('D', 'test', 3),
            ]),
            $collection
        );
    }
}
