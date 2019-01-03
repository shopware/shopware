<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountProfile;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Framework\Page\PageletStruct;

class AccountProfilePageletStruct extends PageletStruct
{
    /**
     * @var CustomerEntity
     */
    protected $customer;

    public function __construct(CustomerEntity $customer = null)
    {
        $this->customer = $customer;
    }

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
