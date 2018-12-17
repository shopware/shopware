<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Struct\Struct;

class CustomerPageStruct extends Struct
{
    /**
     * @var CustomerEntity
     */
    private $customer;

    public function __construct(CustomerEntity $customer)
    {
        $this->customer = $customer;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
