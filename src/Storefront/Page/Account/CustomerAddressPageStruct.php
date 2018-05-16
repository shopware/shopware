<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Framework\Struct\Struct;

class CustomerAddressPageStruct extends Struct
{
    /**
     * @var CustomerAddressBasicCollection
     */
    private $addresses;

    public function __construct(CustomerAddressBasicCollection $addresses)
    {
        $this->addresses = $addresses;
    }

    public function getAddresses(): CustomerAddressBasicCollection
    {
        return $this->addresses;
    }
}
