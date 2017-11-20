<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\Customer\Collection\CustomerBasicCollection;

class CustomerAddressDetailStruct extends CustomerAddressBasicStruct
{
    /**
     * @var CustomerBasicStruct
     */
    protected $customer;

    /**
     * @var CustomerBasicCollection
     */
    protected $customers;

    public function __construct()
    {
        $this->customers = new CustomerBasicCollection();
    }

    public function getCustomer(): CustomerBasicStruct
    {
        return $this->customer;
    }

    public function setCustomer(CustomerBasicStruct $customer): void
    {
        $this->customer = $customer;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerBasicCollection $customers): void
    {
        $this->customers = $customers;
    }
}
