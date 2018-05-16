<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Struct;

use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Checkout\Order\Collection\OrderBasicCollection;

class CustomerDetailStruct extends CustomerBasicStruct
{
    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection
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
