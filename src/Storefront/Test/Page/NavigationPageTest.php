<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageTest extends TestCase
{
    use IntegrationTestBehaviour,
        StorefrontPageTestBehaviour;

    public function testItThrowsWithoutNavigation(): void
    {
        $this->assertFailsWithoutNavigation();
    }

    public function testItDoesLoadAPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        /** @var NavigationPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::NAME, $event);

        $this->expectException(\TypeError::class);
        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(NavigationPage::class, $page);
        self::assertPageEvent(NavigationPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return NavigationPageLoader
     */
    protected function getPageLoader(): PageLoaderInterface
    {
        return $this->getContainer()->get(NavigationPageLoader::class);
    }
}
