<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Storefront\Pagelet\Pagelet;

class GuestWishlistPagelet extends Pagelet
{
    /**
     * @var ProductListResponse
     */
    protected $searchResult;

    public function getSearchResult(): ProductListResponse
    {
        return $this->searchResult;
    }

    public function setSearchResult(ProductListResponse $searchResult): void
    {
        $this->searchResult = $searchResult;
    }
}
