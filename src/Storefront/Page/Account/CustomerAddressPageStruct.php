<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressBasicCollection;
use Shopware\Core\Framework\Struct\Struct;

class CustomerAddressPageStruct extends Struct
{
    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressBasicCollection
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
