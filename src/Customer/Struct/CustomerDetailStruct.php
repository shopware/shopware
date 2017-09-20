<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Shop\Struct\ShopBasicStruct;

class CustomerDetailStruct extends CustomerBasicStruct
{
    /**
     * @var CustomerAddressBasicCollection
     */
    protected $addresses;

    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    public function __construct()
    {
        $this->addresses = new CustomerAddressBasicCollection();
    }

    public function getAddresses(): CustomerAddressBasicCollection
    {
        return $this->addresses;
    }

    public function setAddresses(CustomerAddressBasicCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getShop(): ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }
}
