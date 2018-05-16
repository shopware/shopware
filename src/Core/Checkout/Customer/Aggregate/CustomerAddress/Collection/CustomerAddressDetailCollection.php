<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection;

use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressDetailStruct;
use Shopware\Checkout\Customer\Collection\CustomerBasicCollection;

class CustomerAddressDetailCollection extends CustomerAddressBasicCollection
{
    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressDetailStruct[]
     */
    protected $elements = [];

    public function getCustomers(): CustomerBasicCollection
    {
        return new CustomerBasicCollection(
            $this->fmap(function (CustomerAddressDetailStruct $customerAddress) {
                return $customerAddress->getCustomer();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CustomerAddressDetailStruct::class;
    }
}
