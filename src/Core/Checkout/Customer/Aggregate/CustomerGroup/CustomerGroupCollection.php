<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(CustomerGroupEntity $entity)
 * @method void                     set(string $key, CustomerGroupEntity $entity)
 * @method CustomerGroupEntity[]    getIterator()
 * @method CustomerGroupEntity[]    getElements()
 * @method CustomerGroupEntity|null get(string $key)
 * @method CustomerGroupEntity|null first()
 * @method CustomerGroupEntity|null last()
 */
class CustomerGroupCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupEntity::class;
    }
}
