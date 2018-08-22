<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Framework\ORM\Entity;

class OrderCustomerStruct extends Entity
{
    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var CustomerStruct
     */
    protected $customer;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomer(): CustomerStruct
    {
        return $this->customer;
    }

    public function setCustomer(CustomerStruct $customer): void
    {
        $this->customer = $customer;
    }
}
