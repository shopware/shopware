<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\MainCategory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MainCategoryEntity::class)]
class MainCategoryEntityTest extends TestCase
{
    public function testGetCategoryFallsBackToAnEmptyCategory(): void
    {
        static::assertEquals(new CategoryEntity(), (new MainCategoryEntity())->getCategory());
    }

    public function testGetCategoryReturnsTheAssignedCategory(): void
    {
        $mainCategory = new MainCategoryEntity();
        $category = new CategoryEntity();
        $category->setId('category-id');
        $mainCategory->setCategory($category);

        static::assertSame($category, $mainCategory->getCategory());
    }
}
