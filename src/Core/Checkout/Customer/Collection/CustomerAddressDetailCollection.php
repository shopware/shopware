<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Collection;

use Shopware\Checkout\Customer\Struct\CustomerAddressDetailStruct;

class CustomerAddressDetailCollection extends CustomerAddressBasicCollection
{
    /**
     * @var CustomerAddressDetailStruct[]
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
