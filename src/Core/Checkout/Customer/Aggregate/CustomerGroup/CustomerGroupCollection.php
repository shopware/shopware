<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Framework\ORM\EntityCollection;

class CustomerGroupCollection extends EntityCollection
{
    /**
     * @var CustomerGroupStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupStruct::class;
    }
}
