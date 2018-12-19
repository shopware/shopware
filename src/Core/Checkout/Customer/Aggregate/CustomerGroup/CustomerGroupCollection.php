<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CustomerGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomerGroupEntity::class;
    }
}
