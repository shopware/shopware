<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoader;

class SearchPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testitRequiresSearchParam(): void
    {
        /** @var PageLoaderInterface $page */
        $request = new InternalRequest();
        $context = $this->createCheckoutContextWithNavigation();

        $this->expectParamMissingException('search');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesSearch(): void
    {
        /** @var PageLoaderInterface $page */
        $request = new InternalRequest(['search' => 'foo']);
        $context = $this->createCheckoutContextWithNavigation();
        /** @var SearchPageLoadedEvent $homePageLoadedEvent */
        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::NAME, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(SearchPage::class, $page);
        static::assertEmpty($page->getListing()); //@todo should this be null, but empty collection?
        static::assertEquals('foo', $page->getSearchTerm());
        self::assertPageEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent, $context, $request, $page);
    }

    /**
     * @return SearchPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(SearchPageLoader::class);
    }
}
