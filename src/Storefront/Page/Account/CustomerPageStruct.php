<?php


namespace Shopware\Storefront\Page\Account;


use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Struct\Struct;

class CustomerPageStruct extends Struct
{

    /**
     * @var CustomerBasicStruct
     */
    private $customer;

    public function __construct(CustomerBasicStruct $customer)
    {

        $this->customer = $customer;
    }

    public function getCustomer(): CustomerBasicStruct
    {
        return $this->customer;
    }

}