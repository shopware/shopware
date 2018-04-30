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

namespace Shopware\Cart\Test\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\LineItem\LineItemCollection;

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
            new LineItem('A', 'a', 1),
            new LineItem('A', 'a', 2),
            new LineItem('A', 'a', 3),
        ]);

        static::assertEquals(
            new LineItemCollection([
                new LineItem('A', 'a', 6),
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
        ], $collection->getIdentifiers());
    }

    public function testFillCollectionWithItems(): void
    {
        $collection = new LineItemCollection();
        $collection->fill([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]);

        static::assertEquals(new LineItemCollection([
            new LineItem('A', 'a', 1),
            new LineItem('B', 'a', 1),
            new LineItem('C', 'a', 1),
        ]), $collection);
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

        $this->assertEquals(
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

        $this->assertTrue($collection->exists($first));
        $this->assertFalse($collection->exists($second));
    }

    public function testGetCollectivePayload(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 'temp', 1, ['foo' => 'bar']),
            new LineItem('B', 'temp', 1, ['bar' => 'foo']),
        ]);

        $this->assertEquals(
            [
                'A' => ['foo' => 'bar'],
                'B' => ['bar' => 'foo'],
            ],
            $collection->getPayload()
        );
    }
}
