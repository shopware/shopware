<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Core\Framework\Struct\Struct;

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
