<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\HttpFoundation\Request;

class SearchPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private const TEST_TERM = 'foo';

    public function testitRequiresSearchParam(): void
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
        /** @var SearchPageLoadedEvent $homePageLoadedEvent */
        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(SearchPage::class, $page);
        static::assertEmpty($page->getListing());
        static::assertSame(self::TEST_TERM, $page->getSearchTerm());
        self::assertPageEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent, $context, $request, $page);
    }

    /**
     * @return SearchPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(SearchPageLoader::class);
    }
}
