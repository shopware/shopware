<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPagelet;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Symfony\Component\HttpFoundation\Request;

class GuestWishlistPageletTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsWishlistGuestPageletWithInvalidProductIds(): void
    {
        static::expectException(\InvalidArgumentException::class);
        $request = new Request();
        $context = $this->createSalesChannelContext();

        $request->request->set('productIds', 'invalid value');

        $this->getPageLoader()->load($request, $context);
    }

    public function testItLoadsWishlistGuestPagelet(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContext();

        /** @var GuestWishlistPageletLoadedEvent $event */
        $event = null;
        $this->catchEvent(GuestWishlistPageletLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(GuestWishlistPagelet::class, $page);
        static::assertInstanceOf(ProductListResponse::class, $page->getSearchResult());
        self::assertPageletEvent(GuestWishlistPageletLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return GuestWishlistPageletLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(GuestWishlistPageletLoader::class);
    }
}
