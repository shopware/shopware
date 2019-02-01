<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

class CollectionTest extends TestCase
{
    public function testConstructor(): void
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection($elements);

        self::assertEquals($elements, $collection->getElements());
    }

    public function testClear(): void
    {
        $collection = new TestCollection();
        $collection->add('a');
        $collection->add('b');

        $collection->clear();
        self::assertEmpty($collection->getElements());
    }

    public function testCount(): void
    {
        $collection = new TestCollection();
        self::assertEquals(0, $collection->count());

        $collection->add('a');
        $collection->add('b');
        self::assertEquals(2, $collection->count());
    }

    public function testGetNumericKeys(): void
    {
        $collection = new TestCollection();
        self::assertEquals([], $collection->getKeys());

        $collection->add('a');
        $collection->add('b');
        self::assertEquals([0, 1], $collection->getKeys());
    }

    public function testHasWithNumericKey(): void
    {
        $collection = new TestCollection();
        self::assertFalse($collection->has(0));

        $collection->add('a');
        $collection->add('b');
        self::assertTrue($collection->has(0));
        self::assertTrue($collection->has(1));
    }

    public function testMap(): void
    {
        $collection = new TestCollection();
        $collection->map(function () {
            self::fail('map should not be called for empty collection');
        });

        $collection->add('a');
        $collection->add('b');
        $result = $collection->map(function ($element) use (&$processedElements) {
            return $element . '_test';
        });
        self::assertEquals(['a_test', 'b_test'], $result);
    }

    public function testFmap(): void
    {
        $collection = new TestCollection();
        $collection->fmap(function () {
            self::fail('fmap should not be called for empty collection');
        });

        $collection->add('a');
        $collection->add('b');
        $filtered = $collection->fmap(function ($element) {
            return $element === 'a' ? false : $element . '_test';
        });
        self::assertEquals([1 => 'b_test'], $filtered);
    }

    public function testSort(): void
    {
        $collection = new TestCollection();

        $collection->sort(function () {
            self::fail('fmap should not be called for empty collection');
        });

        $collection->add('b');
        $collection->add('c');
        $collection->add('a');

        $collection->sort(function ($a, $b) {
            return strcmp($a, $b);
        });

        self::assertEquals([2 => 'a', 0 => 'b', 1 => 'c'], $collection->getElements());
    }

    public function testFilterInstance(): void
    {
        $productStruct = new ProductEntity();
        $categoryStruct = new CategoryEntity();
        $collection = new TestCollection();
        self::assertEquals(0, $collection->filterInstance(ProductEntity::class)->count());

        $collection->add('a');
        $collection->add($productStruct);
        $collection->add($categoryStruct);

        $filtered = $collection->filterInstance(Struct::class);
        self::assertEquals([$productStruct, $categoryStruct], $filtered->getElements());
    }

    public function testFilter(): void
    {
        $collection = new TestCollection();
        $collection->filter(function () {
            self::fail('filter should not be called for empty collection');
        });

        $collection->add('a');
        $collection->add('b');
        $collection->add('c');

        $filtered = $collection->filter(function ($element) {
            return $element !== 'b';
        });
        self::assertEquals(['a', 'c'], $filtered->getElements());
    }

    public function testSlice(): void
    {
        $collection = new TestCollection();
        self::assertEmpty($collection->slice(0)->getElements());

        $collection->add('a');
        $collection->add('b');
        $collection->add('c');

        self::assertEquals(['b', 'c'], $collection->slice(1)->getElements());
        self::assertEquals(['b'], $collection->slice(1, 1)->getElements());
    }

    public function testGetElements(): void
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        self::assertEquals([], $collection->getElements());

        $collection->add('a');
        $collection->add('b');

        self::assertEquals($elements, $collection->getElements());
    }

    public function testJsonSerialize(): void
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

        $collection->add('a');
        $collection->add('b');

        self::assertEquals(
            [
                'elements' => $elements,
                'extensions' => [],
                '_class' => TestCollection::class,
            ],
            $collection->jsonSerialize()
        );
    }

    public function testFirst(): void
    {
        $collection = new TestCollection();
        self::assertNull($collection->first());

        $collection->add('a');
        $collection->add('b');

        self::assertEquals('a', $collection->first());
    }

    public function testLast(): void
    {
        $collection = new TestCollection();
        self::assertNull($collection->last());

        $collection->add('a');
        $collection->add('b');

        self::assertEquals('b', $collection->last());
    }
}

class TestCollection extends Collection
{
}
