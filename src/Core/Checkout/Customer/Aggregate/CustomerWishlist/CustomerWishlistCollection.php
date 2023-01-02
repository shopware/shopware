<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerWishlistEntity>
 */
#[Package('customer-order')]
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
