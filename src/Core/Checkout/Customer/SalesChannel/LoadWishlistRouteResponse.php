<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('customer-order')]
class LoadWishlistRouteResponse extends StoreApiResponse
{
    /**
     * @var CustomerWishlistEntity
     */
    protected $wishlist;

    /**
     * @var EntitySearchResult
     */
    protected $productListing;

    public function __construct(
        CustomerWishlistEntity $wishlist,
        EntitySearchResult $listing
    ) {
        $this->wishlist = $wishlist;
        $this->productListing = $listing;
        parent::__construct(new ArrayStruct([
            'wishlist' => $wishlist,
            'products' => $listing,
        ], 'wishlist_products'));
    }

    public function getWishlist(): CustomerWishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(CustomerWishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getProductListing(): EntitySearchResult
    {
        return $this->productListing;
    }

    public function setProductListing(EntitySearchResult $productListing): void
    {
        $this->productListing = $productListing;
    }
}
