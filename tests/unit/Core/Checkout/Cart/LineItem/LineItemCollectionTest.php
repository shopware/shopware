<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\LineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemCollection::class)]
class LineItemCollectionTest extends TestCase
{
    public function testCollectionIsCountable(): void
    {
        $collection = new LineItemCollection();
        static::assertCount(0, $collection);
    }

    /**
     * @param array<string, bool> $expectedResults
     */
    #[DataProvider('lineItemStateProvider')]
    public function testHasLineItemWithState(LineItemCollection $collection, array $expectedResults): void
    {
        foreach ($expectedResults as $state => $expected) {
            static::assertSame($expected, $collection->hasLineItemWithState($state), 'Line item of state `' . $state . '` could not be found.');
        }
    }

    public static function lineItemStateProvider(): \Generator
    {
        yield 'collection has line item with state download and physical' => [
            new LineItemCollection([
                (new LineItem('A', 'test'))->setStates([State::IS_PHYSICAL]),
                (new LineItem('B', 'test'))->setStates([State::IS_DOWNLOAD]),
            ]),
            [State::IS_PHYSICAL => true, State::IS_DOWNLOAD => true],
        ];
        yield 'collection has line item with only state physical' => [
            new LineItemCollection([
                (new LineItem('A', 'test'))->setStates([State::IS_PHYSICAL]),
                (new LineItem('B', 'test'))->setStates([State::IS_PHYSICAL]),
            ]),
            [State::IS_PHYSICAL => true, State::IS_DOWNLOAD => false],
        ];
        yield 'collection has line item with only state download' => [
            new LineItemCollection([
                (new LineItem('A', 'test'))->setStates([State::IS_DOWNLOAD]),
                (new LineItem('B', 'test'))->setStates([State::IS_DOWNLOAD]),
            ]),
            [State::IS_PHYSICAL => false, State::IS_DOWNLOAD => true],
        ];
        yield 'collection has line items without any state' => [
            new LineItemCollection([
                new LineItem('A', 'test'),
                new LineItem('B', 'test'),
            ]),
            [State::IS_PHYSICAL => false, State::IS_DOWNLOAD => false],
        ];
        yield 'collection has line items with a unknown state' => [
            new LineItemCollection([
                (new LineItem('A', 'test'))->setStates(['foo']),
                (new LineItem('B', 'test'))->setStates(['foo']),
            ]),
            [State::IS_PHYSICAL => false, State::IS_DOWNLOAD => false, 'foo' => true],
        ];
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
            (new LineItem('A', 'a'))->setStackable(true)->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('A', 'a', null, 2))->setStackable(true),
            (new LineItem('A', 'a', null, 3))->setStackable(true),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'a', null, 6))->setStackable(true)->assign(['uniqueIdentifier' => 'A']),
            ]),
            $collection
        );
    }

    public function testFilterReturnsNewCollectionWithCorrectItems(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A1', 'A'))->assign(['uniqueIdentifier' => 'A1']),
            (new LineItem('A2', 'A'))->assign(['uniqueIdentifier' => 'A2']),
            (new LineItem('B', 'B'))->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('B2', 'B'))->assign(['uniqueIdentifier' => 'B2']),
            (new LineItem('B3', 'B'))->assign(['uniqueIdentifier' => 'B3']),
            (new LineItem('B4', 'B'))->assign(['uniqueIdentifier' => 'B4']),
            (new LineItem('C', 'C'))->assign(['uniqueIdentifier' => 'C']),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A1', 'A'))->assign(['uniqueIdentifier' => 'A1']),
                (new LineItem('A2', 'A'))->assign(['uniqueIdentifier' => 'A2']),
            ]),
            $collection->filterType('A')
        );
        static::assertEquals(
            new LineItemCollection([
                (new LineItem('B', 'B'))->assign(['uniqueIdentifier' => 'B']),
                (new LineItem('B2', 'B'))->assign(['uniqueIdentifier' => 'B2']),
                (new LineItem('B3', 'B'))->assign(['uniqueIdentifier' => 'B3']),
                (new LineItem('B4', 'B'))->assign(['uniqueIdentifier' => 'B4']),
            ]),
            $collection->filterType('B')
        );
        static::assertEquals(
            new LineItemCollection([
                (new LineItem('C', 'C'))->assign(['uniqueIdentifier' => 'C']),
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
            new LineItem('B', 'b'),
            new LineItem('C', 'a'),
        ]);

        static::assertCount(2, $collection->filterType('a'));
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
            (new LineItem('A', 'a'))->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('B', 'a'))->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('C', 'a'))->assign(['uniqueIdentifier' => 'C']),
        ]);
        $collection->remove('A');

        static::assertEquals(new LineItemCollection([
            (new LineItem('B', 'a'))->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('C', 'a'))->assign(['uniqueIdentifier' => 'C']),
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
        $first = (new LineItem('A', 'temp'))->assign(['uniqueIdentifier' => 'A']);

        $collection = new LineItemCollection([
            $first,
            (new LineItem('B', 'temp'))->assign(['uniqueIdentifier' => 'B']),
        ]);

        $collection->removeElement($first);

        static::assertEquals(
            new LineItemCollection([(new LineItem('B', 'temp'))->assign(['uniqueIdentifier' => 'B'])]),
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
            (new LineItem('A', 'test'))->setStackable(true)->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('A', 'test', null, 2))->setStackable(true)->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('A', 'test', null, 3))->setStackable(true)->assign(['uniqueIdentifier' => 'A']),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', null, 6))->setStackable(true)->assign(['uniqueIdentifier' => 'A']),
            ]),
            $collection
        );
    }

    public function testCartThrowsExceptionOnLineItemCollision(): void
    {
        $cart = new Cart('test');

        $cart->add(new LineItem('a', 'first-type'));

        $this->expectException(CartException::class);

        $cart->add(new LineItem('a', 'other-type'));
    }

    public function testGetLineItemByIdentifier(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', null, 3))->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('B', 'test', null, 3))->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('C', 'test', null, 3))->assign(['uniqueIdentifier' => 'C']),
            (new LineItem('D', 'test', null, 3))->assign(['uniqueIdentifier' => 'D']),
        ]);

        static::assertEquals(
            (new LineItem('C', 'test', null, 3))->assign(['uniqueIdentifier' => 'C']),
            $collection->get('C')
        );
    }

    public function testFilterGoodsReturnsOnlyGoods(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', null, 3))->setGood(true)->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('B', 'test', null, 3))->setGood(false)->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('C', 'test', null, 3))->setGood(false)->assign(['uniqueIdentifier' => 'C']),
            (new LineItem('D', 'test', null, 3))->setGood(true)->assign(['uniqueIdentifier' => 'D']),
        ]);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', null, 3))->setGood(true)->assign(['uniqueIdentifier' => 'A']),
                (new LineItem('D', 'test', null, 3))->setGood(true)->assign(['uniqueIdentifier' => 'D']),
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
                'A' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'B' => new CalculatedPrice(300, 300, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]),
            $lineItems->getPrices()
        );
    }

    public function testRemoveWithNoneExistingIdentifier(): void
    {
        $collection = new LineItemCollection([
            (new LineItem('A', 'test', null, 3))->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('B', 'test', null, 3))->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('C', 'test', null, 3))->assign(['uniqueIdentifier' => 'C']),
            (new LineItem('D', 'test', null, 3))->assign(['uniqueIdentifier' => 'D']),
        ]);

        $collection->remove('X');

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', null, 3))->assign(['uniqueIdentifier' => 'A']),
                (new LineItem('B', 'test', null, 3))->assign(['uniqueIdentifier' => 'B']),
                (new LineItem('C', 'test', null, 3))->assign(['uniqueIdentifier' => 'C']),
                (new LineItem('D', 'test', null, 3))->assign(['uniqueIdentifier' => 'D']),
            ]),
            $collection
        );
    }

    public function testRemoveWithNotExisting(): void
    {
        $c = (new LineItem('C', 'test', null, 3))->assign(['uniqueIdentifier' => 'C']);

        $collection = new LineItemCollection([
            (new LineItem('A', 'test', null, 3))->assign(['uniqueIdentifier' => 'A']),
            (new LineItem('B', 'test', null, 3))->assign(['uniqueIdentifier' => 'B']),
            (new LineItem('D', 'test', null, 3))->assign(['uniqueIdentifier' => 'D']),
        ]);

        $collection->removeElement($c);

        static::assertEquals(
            new LineItemCollection([
                (new LineItem('A', 'test', null, 3))->assign(['uniqueIdentifier' => 'A']),
                (new LineItem('B', 'test', null, 3))->assign(['uniqueIdentifier' => 'B']),
                (new LineItem('D', 'test', null, 3))->assign(['uniqueIdentifier' => 'D']),
            ]),
            $collection
        );
    }
}
