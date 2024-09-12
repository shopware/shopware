<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('buyers-experience')]
class SearchPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private const TEST_TERM = 'foo';

    public function testItRequiresSearchParam(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $this->expectParamMissingException('search');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesSearch(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);
        $context = $this->createSalesChannelContextWithNavigation();
        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertEmpty($page->getListing());
        static::assertSame(self::TEST_TERM, $page->getSearchTerm());
        self::assertPageEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent, $context, $request, $page);
    }

    public function testItDoesApplyDefaultSorting(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);

        $context = $this->createSalesChannelContextWithNavigation();

        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(
            'score',
            $page->getListing()->getSorting()
        );
    }

    public function testItDisplaysCorrectTitle(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);

        $context = $this->createSalesChannelContextWithNavigation();

        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame('Searchresult | Demostore', $page->getMetaInformation()?->getMetaTitle());

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.shopName', 'Teststore', $context->getSalesChannel()->getId());

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame('Searchresult | Teststore', $page->getMetaInformation()?->getMetaTitle());
    }

    protected function getPageLoader(): SearchPageLoader
    {
        return $this->getContainer()->get(SearchPageLoader::class);
    }
}
