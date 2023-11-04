<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPage;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoadedEvent;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class GuestWishlistPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsWishlistGuestPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContext();

        /** @var GuestWishlistPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(GuestWishlistPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(GuestWishlistPage::class, $page);
        self::assertPageEvent(GuestWishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return GuestWishlistPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(GuestWishlistPageLoader::class);
    }
}
