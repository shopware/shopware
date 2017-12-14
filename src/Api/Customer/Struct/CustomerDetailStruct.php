<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Struct;

use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CustomerDetailStruct extends CustomerBasicStruct
{
    /**
     * @var ShopBasicStruct
     */
    protected $mainShop;

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $addresses;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    public function __construct()
    {
        $this->addresses = new CustomerAddressBasicCollection();

        $this->orders = new OrderBasicCollection();
    }

    public function getMainShop(): ShopBasicStruct
    {
        return $this->mainShop;
    }

    public function setMainShop(ShopBasicStruct $mainShop): void
    {
        $this->mainShop = $mainShop;
    }

    public function getAddresses(): CustomerAddressBasicCollection
    {
        return $this->addresses;
    }

    public function setAddresses(CustomerAddressBasicCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getOrders(): OrderBasicCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderBasicCollection $orders): void
    {
        $this->orders = $orders;
    }
}
