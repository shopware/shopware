<?php


namespace Shopware\Storefront\Page\Account;


use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
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