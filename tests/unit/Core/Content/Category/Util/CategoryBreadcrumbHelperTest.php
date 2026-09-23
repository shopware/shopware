<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryBreadcrumbHelper::class)]
class CategoryBreadcrumbHelperTest extends TestCase
{
    private const ROOT_ID = 'root-id';
    private const NAVIGATION_ID = 'navigation-id';
    private const SERVICE_ID = 'service-id';
    private const FOOTER_ID = 'footer-id';
    private const CHILD_ID = 'child-id';
    private const LEAF_ID = 'leaf-id';

    public function testReturnsFullBreadcrumbWhenNoSalesChannelAndNoNavigationCategory(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
        ]);

        static::assertSame(
            [
                self::ROOT_ID => 'Root',
                self::NAVIGATION_ID => 'Catalogue',
                self::CHILD_ID => 'Category',
            ],
            CategoryBreadcrumbHelper::build($category)
        );
    }

    public function testSlicesBreadcrumbAfterSalesChannelNavigationCategory(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
            self::LEAF_ID => 'Leaf',
        ]);

        $salesChannel = $this->createSalesChannel(self::NAVIGATION_ID);

        static::assertSame(
            [
                self::CHILD_ID => 'Category',
                self::LEAF_ID => 'Leaf',
            ],
            CategoryBreadcrumbHelper::build($category, $salesChannel)
        );
    }

    public function testSlicesBreadcrumbAfterServiceCategory(): void
    {
        $category = $this->createCategory([
            self::SERVICE_ID => 'Service',
            self::CHILD_ID => 'Category',
        ]);

        $salesChannel = $this->createSalesChannel('unrelated-navigation', self::SERVICE_ID);

        static::assertSame(
            [self::CHILD_ID => 'Category'],
            CategoryBreadcrumbHelper::build($category, $salesChannel)
        );
    }

    public function testSlicesBreadcrumbAfterFooterCategory(): void
    {
        $category = $this->createCategory([
            self::FOOTER_ID => 'Footer',
            self::CHILD_ID => 'Category',
        ]);

        $salesChannel = $this->createSalesChannel('unrelated-navigation', 'unrelated-service', self::FOOTER_ID);

        static::assertSame(
            [self::CHILD_ID => 'Category'],
            CategoryBreadcrumbHelper::build($category, $salesChannel)
        );
    }

    public function testExplicitNavigationCategoryIdTakesPrecedence(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
            self::LEAF_ID => 'Leaf',
        ]);

        $salesChannel = $this->createSalesChannel(self::NAVIGATION_ID);

        static::assertSame(
            [self::LEAF_ID => 'Leaf'],
            CategoryBreadcrumbHelper::build($category, $salesChannel, self::CHILD_ID)
        );
    }

    public function testUsesNavigationCategoryIdWithoutSalesChannel(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
        ]);

        static::assertSame(
            [
                self::CHILD_ID => 'Category',
            ],
            CategoryBreadcrumbHelper::build($category, null, self::NAVIGATION_ID)
        );
    }

    public function testReturnsFullBreadcrumbWhenNoEntryPointMatches(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::CHILD_ID => 'Category',
        ]);

        $salesChannel = $this->createSalesChannel('unknown-navigation');

        static::assertSame(
            [
                self::ROOT_ID => 'Root',
                self::CHILD_ID => 'Category',
            ],
            CategoryBreadcrumbHelper::build($category, $salesChannel)
        );
    }

    /**
     * @param array<string, string> $breadcrumb
     */
    private function createCategory(array $breadcrumb): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(self::LEAF_ID);
        $category->setTranslated(['breadcrumb' => $breadcrumb]);

        return $category;
    }

    private function createSalesChannel(
        string $navigationCategoryId,
        ?string $serviceCategoryId = null,
        ?string $footerCategoryId = null,
    ): SalesChannelEntity {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setNavigationCategoryId($navigationCategoryId);

        if ($serviceCategoryId !== null) {
            $salesChannel->setServiceCategoryId($serviceCategoryId);
        }

        if ($footerCategoryId !== null) {
            $salesChannel->setFooterCategoryId($footerCategoryId);
        }

        return $salesChannel;
    }
}
