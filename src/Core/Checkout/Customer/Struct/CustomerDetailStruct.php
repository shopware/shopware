<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Struct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection;
use Shopware\Core\Checkout\Order\Collection\OrderBasicCollection;

class CustomerDetailStruct extends CustomerBasicStruct
{
    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressBasicCollection
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
