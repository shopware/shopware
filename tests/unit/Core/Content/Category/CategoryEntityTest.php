<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

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

    public function testJsonSerializeContainsTheSortedBreadcrumb(): void
    {
        $category = new CategoryEntity();
        $category->setId('leaf');
        $category->setPath('|root|middle|');
        $category->setTranslated(['breadcrumb' => [
            'middle' => 'Middle',
            'leaf' => 'Leaf',
            'root' => 'Root',
        ]]);

        static::assertSame(['Root', 'Middle', 'Leaf'], $category->getBreadcrumb());

        $data = $category->jsonSerialize();

        static::assertSame(['Root', 'Middle', 'Leaf'], $data['breadcrumb']);
        static::assertIsArray($data['translated']);
        static::assertSame(['Root', 'Middle', 'Leaf'], $data['translated']['breadcrumb']);
    }

    public function testShouldOpenInNewTabOnlyForLinkTypeCategoriesWithTheFlag(): void
    {
        $link = new CategoryEntity();
        $link->setType(CategoryDefinition::TYPE_LINK);
        $link->setTranslated(['linkNewTab' => true]);
        static::assertTrue($link->shouldOpenInNewTab());

        $page = new CategoryEntity();
        $page->setType(CategoryDefinition::TYPE_PAGE);
        $page->setTranslated(['linkNewTab' => true]);
        static::assertFalse($page->shouldOpenInNewTab());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedCmsPageIdSwitchedRoundTrip(): void
    {
        $category = new CategoryEntity();
        $category->setCmsPageIdSwitched(true);

        static::assertTrue($category->getCmsPageIdSwitched());
    }
}
