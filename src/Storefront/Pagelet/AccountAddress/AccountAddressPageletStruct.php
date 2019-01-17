<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\Struct\Struct;

class AccountAddressPageletStruct extends Struct
{
    /**
     * @var CustomerAddressCollection
     */
    private $addresses;

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
