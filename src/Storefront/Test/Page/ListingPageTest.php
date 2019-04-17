<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Listing\ListingPage;
use Shopware\Storefront\Page\Listing\ListingPageLoadedEvent;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
use Symfony\Component\HttpFoundation\Request;

class ListingPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testItLoadsAListing(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);
        $request = new Request(['productId' => $product->getId()]);

        /** @var ListingPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ListingPageLoadedEvent::NAME, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(ListingPage::class, $page);
        static::assertSame(1, $page->getListing()->count());
        self::assertPageEvent(ListingPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return ListingPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(ListingPageLoader::class);
    }
}
