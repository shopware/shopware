<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Shop\Struct\ShopBasicStruct;

class CustomerDetailStruct extends CustomerBasicStruct
{
    /**
     * @var string[]
     */
    protected $addressUuids = [];

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $addresss;

    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    public function __construct()
    {
        $this->addresss = new CustomerAddressBasicCollection();
    }

    public function getAddressUuids(): array
    {
        return $this->addressUuids;
    }

    public function setAddressUuids(array $addressUuids): void
    {
        $this->addressUuids = $addressUuids;
    }

    public function getAddresss(): CustomerAddressBasicCollection
    {
        return $this->addresss;
    }

    public function setAddresss(CustomerAddressBasicCollection $addresss): void
    {
        $this->addresss = $addresss;
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
