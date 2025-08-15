<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieGroupCollection::class)]
class CookieGroupCollectionTest extends TestCase
{
    public function testGetApiAlias(): void
    {
        $collection = new CookieGroupCollection();
        static::assertSame('cookie_group_collection', $collection->getApiAlias());
    }

    public function testCanAddCookieGroup(): void
    {
        $collection = new CookieGroupCollection();
        $group = new CookieGroup(isRequired: true);

        // This should not throw an exception, confirming the expected class is correct
        $collection->add($group);
        static::assertCount(1, $collection);
    }

    public function testCollectionFunctionality(): void
    {
        $collection = new CookieGroupCollection();

        $group1 = new CookieGroup(isRequired: true);
        $group1->snippetName = 'group1';

        $group2 = new CookieGroup(isRequired: false);
        $group2->snippetName = 'group2';

        // Test add
        $collection->add($group1);
        static::assertCount(1, $collection);

        // Test set
        $collection->set('key', $group2);
        static::assertCount(2, $collection);

        // Test get
        static::assertSame($group2, $collection->get('key'));

        // Test has
        static::assertTrue($collection->has('key'));
        static::assertFalse($collection->has('nonexistent'));

        // Test remove
        $collection->remove('key');
        static::assertCount(1, $collection);
        static::assertFalse($collection->has('key'));

        // Test clear
        $collection->clear();
        static::assertCount(0, $collection);
    }

    public function testGetElements(): void
    {
        $collection = new CookieGroupCollection();

        $group1 = new CookieGroup(isRequired: true);
        $group2 = new CookieGroup(isRequired: false);

        $collection->add($group1);
        $collection->add($group2);

        $elements = $collection->getElements();
        static::assertCount(2, $elements);
        static::assertSame($group1, $elements[0]);
        static::assertSame($group2, $elements[1]);
    }

    public function testFirst(): void
    {
        $collection = new CookieGroupCollection();

        // Empty collection
        static::assertNull($collection->first());

        $group1 = new CookieGroup(isRequired: true);
        $group2 = new CookieGroup(isRequired: false);

        $collection->add($group1);
        $collection->add($group2);

        static::assertSame($group1, $collection->first());
    }

    public function testLast(): void
    {
        $collection = new CookieGroupCollection();

        // Empty collection
        static::assertNull($collection->last());

        $group1 = new CookieGroup(isRequired: true);
        $group2 = new CookieGroup(isRequired: false);

        $collection->add($group1);
        $collection->add($group2);

        static::assertSame($group2, $collection->last());
    }
}
