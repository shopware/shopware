<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Storefront\Framework\Page\PageletStruct;

class AccountAddressPageletStruct extends PageletStruct
{
    /**
     * @var CustomerAddressCollection
     */
    private $addresses;

    public function __construct(CustomerAddressCollection $addresses = null)
    {
        $this->addresses = $addresses;
    }

    /**
     * @param CustomerAddressCollection $addresses
     */
    public function setAddresses(CustomerAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getAddresses(): CustomerAddressCollection
    {
        return $this->addresses;
    }
}
