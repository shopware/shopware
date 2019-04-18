<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Home\HomePage;
use Shopware\Storefront\Page\Home\HomePageLoadedEvent;
use Shopware\Storefront\Page\Home\HomePageLoader;
use Symfony\Component\HttpFoundation\Request;

class HomePageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testHomepageLoading(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        /** @var HomePageLoadedEvent $event */
        $event = null;
        $this->catchEvent(HomePageLoadedEvent::NAME, $event);

        $home = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(HomePage::class, $home);
        self::assertPageEvent(HomePageLoadedEvent::class, $event, $context, $request, $home);
    }

    /**
     * @return HomePageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(HomePageLoader::class);
    }
}
