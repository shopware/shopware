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
            new LineItem('A', ''),
            new LineItem('B', ''),
            new LineItem('C', ''),
        ]);
        static::assertCount(3, $collection);
    }

    public function testCollectionStacksSameIdentifier(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'a'))->setStackable(true),
            (new LineItem('A', 'a', null, 2))->setStackable(true),
            (new LineItem('A', 'a', null, 3))->setStackable(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'a', null, 6))->setStackable(true),
            ]),
            $collection
        );
    }

    public function testFilterReturnsNewCollectionWithCorrectItems(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A1', 'A'),
            new LineItem('A2', 'A'),
            new LineItem('B', 'B'),
            new LineItem('B2', 'B'),
            new LineItem('B3', 'B'),
            new LineItem('B4', 'B'),
            new LineItem('C', 'C'),
        ]);

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A1', 'A'),
                new LineItem('A2', 'A'),
            ]),
            $collection->filterType('A')
        );
        static::assertEquals(
            new LineItemCollection([
                new LineItem('B', 'B'),
                new LineItem('B2', 'B'),
                new LineItem('B3', 'B'),
                new LineItem('B4', 'B'),
            ]),
            $collection->filterType('B')
        );
        static::assertEquals(
            new LineItemCollection([
                new LineItem('C', 'C'),
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
            new LineItem('A', 'a'),
            new LineItem('B', 'a'),
            new LineItem('C', 'a'),
        ]);

        static::assertInstanceOf(LineItemCollection::class, $collection->filterType('a'));
    }

    public function testFilterReturnsNewCollection(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a'),
            new LineItem('B', 'a'),
            new LineItem('C', 'a'),
        ]);

        static::assertNotSame($collection, $collection->filterType('a'));
    }

    public function testLineItemsCanBeCleared(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a'),
            new LineItem('B', 'a'),
            new LineItem('C', 'a'),
        ]);
        $collection->clear();
        static::assertEquals(new LineItemCollection(), $collection);
    }

    public function testLineItemsCanBeRemovedByIdentifier(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a'),
            new LineItem('B', 'a'),
            new LineItem('C', 'a'),
        ]);
        $collection->remove('A');

        static::assertEquals(new LineItemCollection([
            new LineItem('B', 'a'),
            new LineItem('C', 'a'),
        ]), $collection);
    }

    public function testIdentifiersCanEasyAccessed(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'a'),
            new LineItem('B', 'a'),
            new LineItem('C', 'a'),
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
        $first = new LineItem('A', 'temp');

        $collection = new LineItemCollection([
            $first,
            new LineItem('B', 'temp'),
        ]);

        $collection->removeElement($first);

        static::assertEquals(
            new LineItemCollection([new LineItem('B', 'temp')]),
            $collection
        );
    }

    public function testExists(): void
    {
        $first = new LineItem('A', 'temp');
        $second = new LineItem('B2', 'temp');

        $collection = new LineItemCollection([
            $first,
            new LineItem('B', 'temp'),
        ]);

        static::assertTrue($collection->exists($first));
        static::assertFalse($collection->exists($second));
    }

    public function testGetCollectivePayload(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'temp'))->setPayload(['foo' => 'bar']),
            (new LineItem('B', 'temp'))->setPayload(['bar' => 'foo']),
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
            (new LineItem('A', 'test', null, 2))->setStackable(true),
            (new LineItem('A', 'test', null, 3))->setStackable(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', null, 6))->setStackable(true),
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
            new LineItem('A', 'test', null, 3),
            new LineItem('B', 'test', null, 3),
            new LineItem('C', 'test', null, 3),
            new LineItem('D', 'test', null, 3),
        ]);

        static::assertEquals(
            new LineItem('C', 'test', null, 3),
            $collection->get('C')
        );
    }

    public function testFilterGoodsReturnsOnlyGoods(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', null, 3))->setGood(true),
            (new LineItem('B', 'test', null, 3))->setGood(false),
            (new LineItem('C', 'test', null, 3))->setGood(false),
            (new LineItem('D', 'test', null, 3))->setGood(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', null, 3))->setGood(true),
                (new LineItem('D', 'test', null, 3))->setGood(true),
            ]),
            $collection->filterGoods()
        );
    }

    public function testFilterGoodsReturnsNewCollection(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', null, 3))->setGood(true),
            (new LineItem('B', 'test', null, 3))->setGood(true),
            (new LineItem('C', 'test', null, 3))->setGood(true),
            (new LineItem('D', 'test', null, 3))->setGood(true),
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
            new LineItem('A', 'test', null, 3),
            new LineItem('B', 'test', null, 3),
            new LineItem('C', 'test', null, 3),
            new LineItem('D', 'test', null, 3),
        ]);

        $collection->remove('X');

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A', 'test', null, 3),
                new LineItem('B', 'test', null, 3),
                new LineItem('C', 'test', null, 3),
                new LineItem('D', 'test', null, 3),
            ]),
            $collection
        );
    }

    public function testRemoveWithNotExisting(): void
    {
        $c = new LineItem('C', 'test', null, 3);

        $collection = new LineItemCollection([
            new LineItem('A', 'test', null, 3),
            new LineItem('B', 'test', null, 3),
            new LineItem('D', 'test', null, 3),
        ]);

        $collection->removeElement($c);

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A', 'test', null, 3),
                new LineItem('B', 'test', null, 3),
                new LineItem('D', 'test', null, 3),
            ]),
            $collection
        );
    }
}
