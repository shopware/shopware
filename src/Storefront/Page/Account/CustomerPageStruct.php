<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Framework\Struct\Struct;

class CustomerPageStruct extends Struct
{
    /**
     * @var CustomerStruct
     */
    private $customer;

    public function __construct(CustomerStruct $customer)
    {
        $this->customer = $customer;
    }

    public function getCustomer(): CustomerStruct
    {
        return $this->customer;
    }
}
