<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class AccountProfilePage extends PageWithHeader
{
    /**
     * @var CustomerEntity
     */
    protected $customer;

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
