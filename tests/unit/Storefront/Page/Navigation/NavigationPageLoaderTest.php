<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Navigation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Navigation\NavigationPage;
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
    public function testItTakesTheBreadcrumbFromTheRoute(): void
    {
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', Uuid::randomHex())]);

        $category = new SalesChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);
        $category->setSeoBreadcrumb($breadcrumb);

        // the route already resolved it, and that is also what registers the path cache tags on this page
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $page = Feature::fake(['BREADCRUMB_REWORK'], fn (): NavigationPage => $this->load($category, $breadcrumbBuilder));

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItFallsBackToTheBuilderForADecoratedRoute(): void
    {
        // CategoryRouteResponse only guarantees a CategoryEntity, which cannot carry the breadcrumb
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', $category->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $page = Feature::fake(['BREADCRUMB_REWORK'], fn (): NavigationPage => $this->load($category, $breadcrumbBuilder));

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItFallsBackWhenTheRouteLeftTheBreadcrumbUnset(): void
    {
        // the entity can carry it, but a decorated route may simply not populate it
        $category = new SalesChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', $category->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $page = Feature::fake(['BREADCRUMB_REWORK'], fn (): NavigationPage => $this->load($category, $breadcrumbBuilder));

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItSkipsTheRouteBreadcrumbWhileTheReworkIsInactive(): void
    {
        $category = new SalesChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        $request = new Request();
        $request->attributes->set('navigationId', $category->getId());

        // the storefront cannot use it yet, so the route must not spend queries on it
        Feature::fake([], fn () => $this->load($category, static::createStub(CategoryBreadcrumbBuilder::class), $request));

        static::assertTrue($request->attributes->get(CategoryRoute::SKIP_BREADCRUMB));
    }

    private function load(CategoryEntity $category, CategoryBreadcrumbBuilder $breadcrumbBuilder, ?Request $request = null): NavigationPage
    {
        $context = Generator::generateSalesChannelContext();

        $request ??= new Request();
        $request->attributes->set('navigationId', $category->getId());

        $categoryRoute = static::createStub(AbstractCategoryRoute::class);
        $categoryRoute->method('load')->willReturn(new CategoryRouteResponse($category));

        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        $loader = new NavigationPageLoader(
            $genericLoader,
            new EventDispatcher(),
            $categoryRoute,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $breadcrumbBuilder,
        );

        return $loader->load($request, $context);
    }
}
