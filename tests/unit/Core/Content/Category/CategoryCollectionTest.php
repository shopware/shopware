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
