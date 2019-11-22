<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(CustomerRecoveryEntity $entity)
 * @method CustomerRecoveryEntity[]    getIterator()
 * @method CustomerRecoveryEntity[]    getElements()
 * @method CustomerRecoveryEntity|null get(string $key)
 * @method CustomerRecoveryEntity|null first()
 * @method CustomerRecoveryEntity|null last()
 */
class CustomerRecoveryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomerRecoveryEntity::class;
    }
}
