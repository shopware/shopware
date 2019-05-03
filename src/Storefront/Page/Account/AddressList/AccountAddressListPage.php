<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\AddressList;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class AccountAddressListPage extends PageWithHeader
{
    /**
     * @var CustomerEntity|null
     */
    protected $customer;

    /**
     * @var EntitySearchResult
     */
    protected $addresses;

    public function getAddresses(): EntitySearchResult
    {
        return $this->addresses;
    }

    public function setAddresses(EntitySearchResult $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function setCustomer(CustomerEntity $customer)
    {
        $this->customer = $customer;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }
}
