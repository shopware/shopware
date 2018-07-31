<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

class CollectionTest extends TestCase
{
    public function testFill()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        $collection->fill($elements);

        self::assertEquals($elements, $collection->getElements());
    }

    public function testClear()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        $collection->fill($elements);

        $collection->clear();
        self::assertEmpty($collection->getElements());
    }

    public function testCount()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertEquals(0, $collection->count());

        $collection->fill($elements);
        self::assertEquals(2, $collection->count());
    }

    public function testGetNumericKeys()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertEquals([], $collection->getKeys());

        $collection->fill($elements);
        self::assertEquals([0, 1], $collection->getKeys());
    }

    public function testGetNonNumericKeys()
    {
        $elements = ['a', 'b'];
        $collection = new NonNumericKeyCollection();
        self::assertEquals([], $collection->getKeys());

        $collection->fill($elements);
        self::assertEquals(['a', 'b'], $collection->getKeys());
    }

    public function testHasWithNumericKey()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertFalse($collection->has(0));

        $collection->fill($elements);
        self::assertTrue($collection->has(0));
        self::assertTrue($collection->has(1));
    }

    public function testHasWithNonNumericKey()
    {
        $elements = ['a', 'b'];
        $collection = new NonNumericKeyCollection();
        self::assertFalse($collection->has('a'));

        $collection->fill($elements);
        self::assertTrue($collection->has('a'));
        self::assertTrue($collection->has('b'));
    }

    public function testMap()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        $collection->map(function () {
            self::fail('map should not be called for empty collection');
        });

        $collection->fill($elements);
        $result = $collection->map(function ($element) use (&$processedElements) {
            return $element . '_test';
        });
        self::assertEquals(['a_test', 'b_test'], $result);
    }

    public function testFmap()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        $collection->fmap(function () {
            self::fail('fmap should not be called for empty collection');
        });

        $collection->fill($elements);
        $filtered = $collection->fmap(function ($element) {
            return $element === 'a' ? false : $element . '_test';
        });
        self::assertEquals([1 => 'b_test'], $filtered);
    }

    public function testSort()
    {
        $elements = ['b', 'c', 'a'];
        $collection = new TestCollection();

        $collection->sort(function () {
            self::fail('fmap should not be called for empty collection');
        });

        $collection->fill($elements);

        $collection->sort(function ($a, $b) {
            return strcmp($a, $b);
        });

        self::assertEquals([2 => 'a', 0 => 'b', 1 => 'c'], $collection->getElements());
    }

    public function testFilterInstance()
    {
        $productStruct = new ProductStruct();
        $categoryStruct = new CategoryStruct();
        $elements = ['a', $productStruct, $categoryStruct];
        $collection = new ObjectTestCollection();
        self::assertEquals(0, ($collection->filterInstance(ProductStruct::class))->count());

        $collection->fill($elements);
        $filtered = $collection->filterInstance(Struct::class);
        self::assertEquals([$productStruct, $categoryStruct], $filtered->getElements());
    }

    public function testFilter()
    {
        $elements = ['a', 'b', 'c'];
        $collection = new TestCollection();
        $collection->filter(function () {
            self::fail('filter should not be called for empty collection');
        });

        $collection->fill($elements);
        $filtered = $collection->filter(function ($element) {
            return $element !== 'b';
        });
        self::assertEquals(['a', 'c'], $filtered->getElements());
    }

    public function testSlice()
    {
        $elements = ['a', 'b', 'c'];
        $collection = new TestCollection();
        self::assertEmpty($collection->slice(0)->getElements());

        $collection->fill($elements);
        self::assertEquals(['b', 'c'], $collection->slice(1)->getElements());
        self::assertEquals(['b'], $collection->slice(1, 1)->getElements());
    }

    public function testGetElements()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertEquals([], $collection->getElements());

        $collection->fill($elements);
        self::assertEquals($elements, $collection->getElements());
    }

    public function testJsonSerialize()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertEquals(
            [
                'elements' => [],
                'extensions' => [],
                '_class' => TestCollection::class,
            ],
            $collection->jsonSerialize()
        );

        $collection->fill($elements);
        self::assertEquals(
            [
                'elements' => $elements,
                'extensions' => [],
                '_class' => TestCollection::class,
            ],
            $collection->jsonSerialize()
        );
    }

    public function testFirst()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertNull($collection->first());

        $collection->fill($elements);
        self::assertEquals('a', $collection->first());
    }

    public function testLast()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertNull($collection->first());

        $collection->fill($elements);
        self::assertEquals('a', $collection->first());
    }

    public function testOffsetExists()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertFalse($collection->offsetExists(0));

        $collection->fill($elements);
        self::assertTrue($collection->offsetExists(0));
        self::assertTrue($collection->offsetExists(1));
    }

    public function testOffsetGet()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        $collection->fill($elements);

        self::assertEquals('a', $collection->offsetGet(0));
        self::assertEquals('b', $collection->offsetGet(1));
    }

    public function testOffsetUnset()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        $collection->fill($elements);

        $collection->offsetUnset(1);
        self::assertEquals(['a'], $collection->getElements());
    }

    public function testCurrent()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertFalse($collection->current());

        $collection->fill($elements);
        self::assertEquals('a', $collection->current());
    }

    public function testNext()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertFalse($collection->next());

        $collection->fill($elements);
        self::assertEquals('b', $collection->next());
    }

    public function testKey()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertNull($collection->key());

        $collection->fill($elements);
        self::assertSame(0, $collection->key());
    }

    public function testRewind()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();

        $collection->fill($elements);
        $collection->next();
        self::assertSame(1, $collection->key());

        $collection->rewind();
        self::assertEquals(0, $collection->key());
    }

    public function testValid()
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertFalse($collection->valid());

        $collection->fill($elements);
        self::assertTrue($collection->valid());

        $collection->next();
        self::assertTrue($collection->valid());

        $collection->offsetUnset(1);
        self::assertFalse($collection->valid());
    }
}

class NonNumericKeyCollection extends Collection
{
    /**
     * @var string[]
     */
    protected $elements = [];

    public function add(string $element): void
    {
        $this->elements[$element] = $element;
    }
}

class ObjectTestCollection extends Collection
{
    /**
     * @var array
     */
    protected $elements = [];

    public function add($element): void
    {
        $this->elements[] = $element;
    }
}

class TestCollection extends Collection
{
    /**
     * @var string[]
     */
    protected $elements = [];

    public function add(string $element): void
    {
        $this->elements[] = $element;
    }
}
