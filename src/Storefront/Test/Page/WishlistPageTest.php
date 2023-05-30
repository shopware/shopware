<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Wishlist\WishlistPage;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoadedEvent;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class WishlistPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testInActiveWishlist(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $this->expectException(CustomerWishlistNotActivatedException::class);
        $this->getPageLoader()->load($request, $context, $context->getCustomer());
    }

    public function testWishlistNotFound(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $page = $this->getPageLoader()->load($request, $context, $this->createCustomer());

        static::assertInstanceOf(WishlistPage::class, $page);
        static::assertSame(0, $page->getWishlist()->getProductListing()->getTotal());
    }

    public function testItLoadsWishlistPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $product = $this->getRandomProduct($context);
        $this->createCustomerWishlist($context->getCustomer()->getId(), $product->getId(), $context->getSalesChannel()->getId());

        /** @var WishlistPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(WishlistPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context, $context->getCustomer());

        static::assertInstanceOf(WishlistPage::class, $page);
        static::assertSame(1, $page->getWishlist()->getProductListing()->getTotal());
        static::assertSame($context->getContext(), $page->getWishlist()->getProductListing()->getContext());
        self::assertPageEvent(WishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

    /**
     * @return WishlistPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(WishlistPageLoader::class);
    }

    private function createCustomerWishlist(string $customerId, string $productId, string $salesChannelId): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = $this->getContainer()->get('customer_wishlist.repository');

        $customerWishlistRepository->create([
            [
                'id' => $customerWishlistId,
                'customerId' => $customerId,
                'salesChannelId' => $salesChannelId,
                'products' => [
                    [
                        'productId' => $productId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $customerWishlistId;
    }
}
