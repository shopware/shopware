<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerRecoveryEntity>
 */
#[Package('customer-order')]
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
