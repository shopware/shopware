<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
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

        self::assertPageEvent(NavigationPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDeniesAccessToInactiveCategoryPage(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $repository = $this->getContainer()->get('category.repository');

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
        $seoUrlHandler = $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);
        $meta = $metaInformation->getVars();
        $canonical = $meta['canonical'];

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertEquals('/', $seoUrl);
    }

    protected function getPageLoader(): NavigationPageLoader
    {
        return $this->getContainer()->get(NavigationPageLoader::class);
    }
}
