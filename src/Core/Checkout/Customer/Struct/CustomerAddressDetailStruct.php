<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

class CustomerAddressDetailStruct extends CustomerAddressBasicStruct
{
    /**
     * @var CustomerBasicStruct
     */
    protected $customer;

    public function getCustomer(): CustomerBasicStruct
    {
        return $this->customer;
    }

    public function setCustomer(CustomerBasicStruct $customer): void
    {
        $this->customer = $customer;
    }
}
