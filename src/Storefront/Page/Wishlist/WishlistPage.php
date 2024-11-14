<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class WishlistPage extends Page
{
    /**
     * @var LoadWishlistRouteResponse
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $wishlist;

    public function getWishlist(): LoadWishlistRouteResponse
    {
        return $this->wishlist;
    }

    public function setWishlist(LoadWishlistRouteResponse $wishlist): void
    {
        $this->wishlist = $wishlist;
    }
}
