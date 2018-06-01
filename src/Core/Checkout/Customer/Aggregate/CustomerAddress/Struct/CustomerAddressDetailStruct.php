<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Struct;

use Shopware\Core\Checkout\Customer\Struct\CustomerBasicStruct;

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
