<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerWishlistProductEntity>
 */
#[Package('customer-order')]
class CustomerWishlistProductCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wishlist_product_collection';
    }

    public function getProducts(): ?ProductCollection
    {
        return new ProductCollection($this->fmap(fn (CustomerWishlistProductEntity $wishlistProductEntity) => $wishlistProductEntity->getProduct()));
    }

    public function getByProductId(string $productId): ?CustomerWishlistProductEntity
    {
        return $this->filterByProperty('productId', $productId)->first();
    }

    protected function getExpectedClass(): string
    {
        return CustomerWishlistProductEntity::class;
    }
}
