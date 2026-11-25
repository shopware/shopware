<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Navigation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationPageLoader::class)]
class NavigationPageLoaderTest extends TestCase
{
    private const CANONICAL = '124c71d524604ccbad6042edce3ac799/navigation/category';

    #[DataProvider('listingPageProvider')]
    public function testListingPageDefinesCanonicalAndTitle(int $listingPage, string $expectedCanonical, string $expectedTitle): void
    {
        $category = $this->createCategory(new CmsPageEntity());
        $category->getCmsPage()?->setSections($this->createSections(new TextStruct(), $this->createListing($listingPage)));

        $metaInformation = $this->load($category, ['p' => (string) $listingPage]);

        static::assertSame($expectedCanonical, $metaInformation->getCanonical());
        static::assertSame($expectedTitle, $metaInformation->getMetaTitle());
        static::assertSame('All seasonal decoration', $metaInformation->getMetaDescription());
    }

    public static function listingPageProvider(): \Generator
    {
        yield 'first listing page keeps the clean canonical and the category title' => [
            1,
            self::CANONICAL,
            'Seasonal decoration',
        ];

        yield 'following listing page gets its own canonical and the page number in the title' => [
            2,
            self::CANONICAL . '?p=2',
            'Seasonal decoration – Page 2',
        ];
    }

    public function testPageNumberIsIgnoredWithoutProductListing(): void
    {
        $category = $this->createCategory(new CmsPageEntity());
        $category->getCmsPage()?->setSections($this->createSections(new TextStruct()));

        $metaInformation = $this->load($category, ['p' => '2']);

        static::assertSame(self::CANONICAL, $metaInformation->getCanonical());
        static::assertSame('Seasonal decoration', $metaInformation->getMetaTitle());
    }

    public function testPageNumberIsIgnoredWithoutCmsPage(): void
    {
        $metaInformation = $this->load($this->createCategory(null), ['p' => '2']);

        static::assertSame(self::CANONICAL, $metaInformation->getCanonical());
        static::assertSame('Seasonal decoration', $metaInformation->getMetaTitle());
    }

    /**
     * @param array<string, string> $query
     */
    private function load(CategoryEntity $category, array $query): MetaInformation
    {
        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturnCallback(static function (): Page {
            $page = new Page();
            $page->setMetaInformation(new MetaInformation());

            return $page;
        });

        $categoryRoute = static::createStub(AbstractCategoryRoute::class);
        $categoryRoute->method('load')->willReturn(new CategoryRouteResponse($category));

        $seoUrlReplacer = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturn(self::CANONICAL);

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters): string => strtr('%content% – Page %page%', $parameters)
        );

        $loader = new NavigationPageLoader(
            $genericLoader,
            new EventDispatcher(),
            $categoryRoute,
            $seoUrlReplacer,
            static::createStub(CategoryBreadcrumbBuilder::class),
            $translator,
        );

        $request = new Request($query, [], ['navigationId' => $category->getId()]);
        $metaInformation = $loader->load($request, Generator::generateSalesChannelContext())->getMetaInformation();
        static::assertInstanceOf(MetaInformation::class, $metaInformation);

        return $metaInformation;
    }

    private function createCategory(?CmsPageEntity $cmsPage): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);
        $category->setTranslated([
            'name' => 'Category name',
            'metaTitle' => 'Seasonal decoration',
            'metaDescription' => 'All seasonal decoration',
        ]);

        if ($cmsPage !== null) {
            $category->setCmsPage($cmsPage);
        }

        return $category;
    }

    private function createListing(int $page): ProductListingStruct
    {
        $criteria = (new Criteria())->setLimit(24)->setOffset(($page - 1) * 24);

        $listing = new ProductListingStruct();
        $listing->setListing(new ProductListingResult('product', 100, new ProductCollection(), null, $criteria, Context::createDefaultContext()));

        return $listing;
    }

    private function createSections(Struct ...$slotData): CmsSectionCollection
    {
        $slots = new CmsSlotCollection();
        foreach ($slotData as $data) {
            $slot = new CmsSlotEntity();
            $slot->setId(Uuid::randomHex());
            $slot->setSlot('content');
            $slot->setData($data);
            $slots->add($slot);
        }

        $block = new CmsBlockEntity();
        $block->setId(Uuid::randomHex());
        $block->setSlots($slots);

        $section = new CmsSectionEntity();
        $section->setId(Uuid::randomHex());
        $section->setBlocks(new CmsBlockCollection([$block]));

        return new CmsSectionCollection([$section]);
    }
}
