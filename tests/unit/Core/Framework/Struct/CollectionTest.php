<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[CoversClass(Collection::class)]
class CollectionTest extends TestCase
{
    public function testConstructor(): void
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection($elements);

        static::assertSame($elements, $collection->getElements());
    }

    public function testConstructorKeepingKeys(): void
    {
        $elements = ['z' => 'a', 'y' => 'b'];
        $collection = new TestCollection($elements);

        static::assertSame($elements, $collection->getElements());
    }

    public function testClear(): void
    {
        $collection = new TestCollection();
        $collection->add('a');
        $collection->add('b');

        $collection->clear();
        static::assertEmpty($collection->getElements());
    }

    public function testCount(): void
    {
        $collection = new TestCollection();
        static::assertCount(0, $collection);

        $collection->add('a');
        $collection->add('b');
        static::assertCount(2, $collection);
    }

    public function testGetNumericKeys(): void
    {
        $collection = new TestCollection();
        static::assertSame([], $collection->getKeys());

        $collection->add('a');
        $collection->add('b');
        static::assertSame([0, 1], $collection->getKeys());
    }

    public function testHasWithNumericKey(): void
    {
        $collection = new TestCollection();
        static::assertFalse($collection->has(0));

        $collection->add('a');
        $collection->add('b');
        static::assertTrue($collection->has(0));
        static::assertTrue($collection->has(1));
    }

    public function testMap(): void
    {
        $collection = new TestCollection();
        $collection->map(function (): void {
            static::fail('map should not be called for empty collection');
        });

        $collection->add('a');
        $collection->add('b');
        $result = $collection->map(fn ($element) => $element . '_test');
        static::assertSame(['a_test', 'b_test'], $result);
    }

    public function testFmap(): void
    {
        $collection = new TestCollection();
        $collection->fmap(function (): void {
            static::fail('fmap should not be called for empty collection');
        });

        $collection->add('a');
        $collection->add('b');
        $filtered = $collection->fmap(fn ($element) => $element === 'a' ? false : $element . '_test');
        static::assertSame([1 => 'b_test'], $filtered);
    }

    public function testSort(): void
    {
        $collection = new TestCollection();

        $collection->sort(function (): void {
            static::fail('fmap should not be called for empty collection');
        });

        $collection->add('b');
        $collection->add('c');
        $collection->add('a');

        $collection->sort(fn ($a, $b) => strcmp((string) $a, (string) $b));

        static::assertSame([2 => 'a', 0 => 'b', 1 => 'c'], $collection->getElements());
    }

    public function testFilterInstance(): void
    {
        $productStruct = new ProductEntity();
        $categoryStruct = new CategoryEntity();
        $collection = new TestCollection();
        static::assertCount(0, $collection->filterInstance(ProductEntity::class));

        $collection->add('a');
        $collection->add($productStruct);
        $collection->add($categoryStruct);

        $filtered = $collection->filterInstance(Struct::class);
        static::assertSame([$productStruct, $categoryStruct], array_values($filtered->getElements()));
    }

    public function testFilter(): void
    {
        $collection = new TestCollection();
        $collection->filter(function (): void {
            static::fail('filter should not be called for empty collection');
        });

        $collection->add('a');
        $collection->add('b');
        $collection->add('c');

        $filtered = $collection->filter(fn ($element) => $element !== 'b');
        static::assertSame(['a', 'c'], array_values($filtered->getElements()));
    }

    public function testSlice(): void
    {
        $collection = new TestCollection();
        static::assertEmpty($collection->slice(0)->getElements());

        $collection->add('a');
        $collection->add('b');
        $collection->add('c');

        static::assertSame(['b', 'c'], array_values($collection->slice(1)->getElements()));
        static::assertSame(['b'], array_values($collection->slice(1, 1)->getElements()));
    }

    public function testGetElements(): void
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        static::assertSame([], $collection->getElements());

        $collection->add('a');
        $collection->add('b');

        static::assertSame($elements, $collection->getElements());
    }

    public function testJsonSerialize(): void
    {
        $elements = ['a', 'b'];
        $collection = new TestCollection();
        static::assertSame(
            [],
            $collection->jsonSerialize()
        );

        $collection->add('a');
        $collection->add('b');

        static::assertSame(
            $elements,
            $collection->jsonSerialize()
        );
    }

    public function testFirst(): void
    {
        $collection = new TestCollection();
        static::assertNull($collection->first());

        $collection->add('a');
        $collection->add('b');

        static::assertSame('a', $collection->first());
    }

    public function testLast(): void
    {
        $collection = new TestCollection();
        static::assertNull($collection->last());

        $collection->add('a');
        $collection->add('b');

        static::assertSame('b', $collection->last());
    }

    public function testGetAt(): void
    {
        $collection = new TestCollection();
        static::assertFalse($collection->has(0));

        $collection->add('a');
        $collection->add('b');
        static::assertSame('a', $collection->getAt(0));
        static::assertSame('b', $collection->getAt(1));
    }

    public function testFirstWhereWithEmptyCollectionWillReturnNull(): void
    {
        $collection = new TestCollection();
        static::assertNull($collection->firstWhere(fn ($element) => $element === 'a'));
    }

    public function testFirstWhereWithMatchingElementWillReturnFirstElement(): void
    {
        $collection = new TestCollection();
        $collection->add('a1');
        $collection->add('a2');
        $collection->add('a3');
        static::assertSame('a1', $collection->firstWhere(fn ($element) => str_starts_with($element, 'a')));
    }
}

/**
 * @internal
 *
 * @extends Collection<string|ProductEntity|CategoryEntity>
 */
class TestCollection extends Collection
{
}
