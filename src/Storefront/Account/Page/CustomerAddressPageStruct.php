<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\Struct\Struct;

class CustomerAddressPageStruct extends Struct
{
    /**
     * @var CustomerAddressCollection
     */
    private $addresses;

    public function __construct(CustomerAddressCollection $addresses)
    {
        $this->addresses = $addresses;
    }

    public function getAddresses(): CustomerAddressCollection
    {
        return $this->addresses;
    }
}
