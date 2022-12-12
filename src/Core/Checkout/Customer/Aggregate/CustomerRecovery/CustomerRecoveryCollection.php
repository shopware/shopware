<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package customer-order
 *
 * @extends EntityCollection<CustomerRecoveryEntity>
 */
class CustomerRecoveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_recovery_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerRecoveryEntity::class;
    }
}
