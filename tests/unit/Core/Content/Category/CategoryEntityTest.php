<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryEntity::class)]
class CategoryEntityTest extends TestCase
{
    public function testPlainBreadcrumbIsEmptyWithoutATranslatedBreadcrumb(): void
    {
        $category = new CategoryEntity();
        $category->setId('leaf');
        $category->setTranslated([]);

        static::assertSame([], $category->getPlainBreadcrumb());
    }

    public function testPlainBreadcrumbReturnsTheFullBreadcrumbWithoutAPath(): void
    {
        $category = new CategoryEntity();
        $category->setId('leaf');
        $category->setTranslated(['breadcrumb' => ['root' => 'Root', 'leaf' => 'Leaf']]);

        static::assertSame(['root' => 'Root', 'leaf' => 'Leaf'], $category->getPlainBreadcrumb());
    }

    public function testPlainBreadcrumbFiltersToThePathAndItself(): void
    {
        $category = new CategoryEntity();
        $category->setId('leaf');
        $category->setPath('|root|middle|');
        $category->setTranslated(['breadcrumb' => [
            'root' => 'Root',
            'middle' => 'Middle',
            'unrelated' => 'Unrelated',
            'leaf' => 'Leaf',
        ]]);

        static::assertSame(['root' => 'Root', 'middle' => 'Middle', 'leaf' => 'Leaf'], $category->getPlainBreadcrumb());
    }
}
