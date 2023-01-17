<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Content\Product\State;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\LineItem\LineItemCollection
 */
class LineItemCollectionTest extends TestCase
{
    /**
     * @dataProvider lineItemStateProvider
     *
     * @param array<string, bool> $expectedResults
     */
    public function testHasLineItemWithState(LineItemCollection $collection, array $expectedResults): void
    {
        foreach ($expectedResults as $state => $expected) {
            static::assertSame($expected, $collection->hasLineItemWithState($state), 'Line item of state `' . $state . '` could not be found.');
        }
    }

    public function lineItemStateProvider(): \Generator
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
                (new LineItem('A', 'test')),
                (new LineItem('B', 'test')),
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
}
