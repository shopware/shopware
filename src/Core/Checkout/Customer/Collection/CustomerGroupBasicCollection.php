<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Collection;

use Shopware\Checkout\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class CustomerGroupBasicCollection extends EntityCollection
{
    /**
     * @var CustomerGroupBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupBasicStruct::class;
    }
}
