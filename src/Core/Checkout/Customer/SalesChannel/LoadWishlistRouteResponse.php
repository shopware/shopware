<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{wishlist: CustomerWishlistEntity, products: EntitySearchResult<ProductCollection>}>>
 */
#[Package('checkout')]
class LoadWishlistRouteResponse extends StoreApiResponse
{
    /**
     * @param EntitySearchResult<ProductCollection> $productListing
     */
    public function __construct(
        protected CustomerWishlistEntity $wishlist,
        protected EntitySearchResult $productListing,
    ) {
        parent::__construct(new ArrayStruct([
            'wishlist' => $wishlist,
            'products' => $productListing,
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

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function getProductListing(): EntitySearchResult
    {
        return $this->productListing;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $productListing
     */
    public function setProductListing(EntitySearchResult $productListing): void
    {
        $this->productListing = $productListing;
    }
}
