<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Checkout\Customer\SalesChannel\LoadGuestWishlistRouteResponse;
use Shopware\Storefront\Pagelet\Pagelet;

class WishlistGuestPagelet extends Pagelet
{
    /**
     * @var LoadGuestWishlistRouteResponse
     */
    protected $searchResult;

    public function getSearchResult(): LoadGuestWishlistRouteResponse
    {
        return $this->searchResult;
    }

    public function setSearchResult(LoadGuestWishlistRouteResponse $searchResult): void
    {
        $this->searchResult = $searchResult;
    }
}
