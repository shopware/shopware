<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\Customer\CustomerBasicStruct;
use Shopware\Core\Framework\Struct\Struct;

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
