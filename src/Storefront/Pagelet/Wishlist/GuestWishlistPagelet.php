<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\Pagelet;

#[Package('discovery')]
class GuestWishlistPagelet extends Pagelet
{
    protected ProductListResponse $searchResult;

    public function getSearchResult(): ProductListResponse
    {
        return $this->searchResult;
    }

    public function setSearchResult(ProductListResponse $searchResult): void
    {
        $this->searchResult = $searchResult;
    }
}
