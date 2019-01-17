<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Struct\Struct;

class AccountProfilePageletStruct extends Struct
{
    /**
     * @var CustomerEntity
     */
    protected $customer;

    /**
     * @return CustomerEntity
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    /**
     * @param CustomerEntity $customer
     */
    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
