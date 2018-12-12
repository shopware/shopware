<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CustomerGroupCollection extends EntityCollection
{
    /**
     * @var CustomerGroupEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupEntity
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupEntity::class;
    }
}
