<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerAddressUpsertedEvent extends Event implements ShopwareEvent
{
    private CustomerEntity $customer;

    private CustomerAddressEntity $address;

    private Context $context;

    public function __construct(CustomerEntity $customer, CustomerAddressEntity $address, Context $context)
    {
        $this->customer = $customer;
        $this->address = $address;
        $this->context = $context;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getAddress(): CustomerAddressEntity
    {
        return $this->address;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
