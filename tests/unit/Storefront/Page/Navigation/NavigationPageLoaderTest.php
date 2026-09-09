<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Navigation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
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
    public function testItTellsTheRouteToSkipTheBreadcrumb(): void
    {
        // the storefront builds the breadcrumb itself, so the route must not resolve it a second time
        $request = new Request();
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', Uuid::randomHex())]);

        $page = $this->load($request, $breadcrumb);

        static::assertTrue($request->attributes->get(CategoryRoute::SKIP_BREADCRUMB));
        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    private function load(Request $request, BreadcrumbCollection $breadcrumb): NavigationPage
    {
        $context = Generator::generateSalesChannelContext();

        $category = new SalesChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        $request->attributes->set('navigationId', $category->getId());

        $categoryRoute = static::createStub(AbstractCategoryRoute::class);
        $categoryRoute->method('load')->willReturn(new CategoryRouteResponse($category));

        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        $breadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn($breadcrumb);

        $loader = new NavigationPageLoader(
            $genericLoader,
            new EventDispatcher(),
            $categoryRoute,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $breadcrumbBuilder,
        );

        return Feature::fake(
            ['BREADCRUMB_REWORK'],
            static fn (): NavigationPage => $loader->load($request, $context)
        );
    }
}
