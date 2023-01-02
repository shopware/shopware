<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\Pagelet;

#[Package('storefront')]
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
