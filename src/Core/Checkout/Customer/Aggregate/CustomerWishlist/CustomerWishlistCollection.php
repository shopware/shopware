<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(CustomerWishlistEntity $entity)
 * @method void                        set(string $key, CustomerWishlistEntity $entity)
 * @method CustomerWishlistEntity[]    getIterator()
 * @method CustomerWishlistEntity[]    getElements()
 * @method CustomerWishlistEntity|null get(string $key)
 * @method CustomerWishlistEntity|null first()
 * @method CustomerWishlistEntity|null last()
 */
class CustomerWishlistCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wishlist_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerWishlistEntity::class;
    }
}
