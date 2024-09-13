<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoadedEvent;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
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

        $event = null;
        $this->catchEvent(GuestWishlistPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(GuestWishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): GuestWishlistPageLoader
    {
        return $this->getContainer()->get(GuestWishlistPageLoader::class);
    }
}
