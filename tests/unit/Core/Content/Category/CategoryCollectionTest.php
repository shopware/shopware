<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryCollection::class)]
class CategoryCollectionTest extends TestCase
{
    public function testSortByNameUsesNaturalCaseInsensitiveOrder(): void
    {
        $collection = new CategoryCollection([
            $this->category('c', 'Shoes 10'),
            $this->category('a', 'shoes 2'),
            $this->category('b', 'Bags'),
        ]);

        static::assertSame($collection, $collection->sortByName());

        static::assertSame(['b', 'a', 'c'], array_keys($collection->getElements()));
    }

    public function testIdAccessorsAndFilters(): void
    {
        $root = $this->category('root', 'Root');
        $root->setMediaId('media-a');
        $child = $this->category('child', 'Child');
        $child->setParentId('root');
        $child->setMediaId('media-b');
        $other = $this->category('other', 'Other');
        $other->setParentId('elsewhere');

        $collection = new CategoryCollection([$root, $child, $other]);

        static::assertSame(['root', 'elsewhere'], array_values($collection->getParentIds()));
        static::assertSame(['media-a', 'media-b'], array_values($collection->getMediaIds()));
        static::assertSame(['child'], array_keys($collection->filterByParentId('root')->getElements()));
        static::assertSame(['root'], array_keys($collection->filterByMediaId('media-a')->getElements()));
    }

    public function testSortByPositionFollowsTheAfterCategoryChain(): void
    {
        $first = $this->category('first', 'First');
        $first->setId('first');
        $second = $this->category('second', 'Second');
        $second->setId('second');
        $second->setAfterCategoryId('first');
        $third = $this->category('third', 'Third');
        $third->setId('third');
        $third->setAfterCategoryId('second');

        $collection = new CategoryCollection([$third, $first, $second]);

        static::assertSame($collection, $collection->sortByPosition());
        static::assertSame(['first', 'second', 'third'], array_keys($collection->getElements()));
    }

    public function testApiAlias(): void
    {
        static::assertSame('category_collection', (new CategoryCollection())->getApiAlias());
    }

    private function category(string $id, string $name): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setUniqueIdentifier($id);
        $category->setTranslated(['name' => $name]);

        return $category;
    }
}
