<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(CustomerWishlistProductEntity $entity)
 * @method void                               set(string $key, CustomerWishlistProductEntity $entity)
 * @method CustomerWishlistProductEntity[]    getIterator()
 * @method CustomerWishlistProductEntity[]    getElements()
 * @method CustomerWishlistProductEntity|null get(string $key)
 * @method CustomerWishlistProductEntity|null first()
 * @method CustomerWishlistProductEntity|null last()
 */
class CustomerWishlistProductCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wishlist_product_collection';
    }

    public function getProducts(): ?ProductCollection
    {
        return new ProductCollection($this->fmap(function (CustomerWishlistProductEntity $wishlistProductEntity) {
            return $wishlistProductEntity->getProduct();
        }));
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
