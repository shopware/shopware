<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class NavigationPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItDoesLoadAPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CategoryEntity::class, $page->getCategory());
        static::assertPageEvent(NavigationPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDeniesAccessToInactiveCategoryPage(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $repository = static::getContainer()->get('category.repository');

        $categoryId = $context->getSalesChannel()->getNavigationCategoryId();

        $repository->update([[
            'id' => $categoryId,
            'active' => false,
        ]], $context->getContext());

        $request = new Request([], [], ['navigationId' => $categoryId]);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $this->expectException(CategoryNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesHaveCanonicalTag(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();
        $seoUrlHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);
        $meta = $metaInformation->getVars();
        $canonical = $meta['canonical'];

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertSame('/', $seoUrl);
    }

    /**
     * The canonical is the single source of truth for the pagination parameter: it is only
     * appended above the first page. The storefront template must render it verbatim, so this
     * pins the loader contract that prevents the duplicated `?p=N?p=N` regression from #19094.
     */
    #[DataProvider('canonicalPaginationProvider')]
    public function testCanonicalContainsPaginationParameterOnlyAboveFirstPage(?string $page, string $expectedSeoUrl): void
    {
        $request = new Request($page === null ? [] : ['p' => $page]);
        $context = $this->createSalesChannelContextWithNavigation();
        $seoUrlHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);

        $canonical = $metaInformation->getCanonical();
        static::assertNotNull($canonical);

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertSame($expectedSeoUrl, $seoUrl);
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string}>
     */
    public static function canonicalPaginationProvider(): iterable
    {
        yield 'no pagination parameter yields the clean canonical' => [null, '/'];
        yield 'first page yields the clean canonical without ?p=1' => ['1', '/'];
        yield 'second page appends ?p=2 exactly once' => ['2', '/?p=2'];
    }

    protected function getPageLoader(): NavigationPageLoader
    {
        return static::getContainer()->get(NavigationPageLoader::class);
    }
}
