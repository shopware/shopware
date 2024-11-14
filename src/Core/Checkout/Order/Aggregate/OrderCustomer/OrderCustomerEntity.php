<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Salutation\SalutationEntity;

#[Package('checkout')]
class OrderCustomerEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $email;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $orderId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salutationId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $firstName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $lastName;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $title;

    /**
     * @var array|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $vatIds;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $company;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customerNumber;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customerId;

    /**
     * @var CustomerEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customer;

    /**
     * @var SalutationEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salutation;

    /**
     * @var OrderEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $order;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $remoteAddress;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $orderVersionId;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getSalutationId(): ?string
    {
        return $this->salutationId;
    }

    public function setSalutationId(string $salutationId): void
    {
        $this->salutationId = $salutationId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getVatIds(): ?array
    {
        return $this->vatIds;
    }

    /**
     * @param array<string> $vatIds
     */
    public function setVatIds(array $vatIds): void
    {
        $this->vatIds = $vatIds;
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(string $customerNumber): void
    {
        $this->customerNumber = $customerNumber;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getSalutation(): ?SalutationEntity
    {
        return $this->salutation;
    }

    public function setSalutation(SalutationEntity $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    public function getRemoteAddress(): ?string
    {
        return $this->remoteAddress;
    }

    public function setRemoteAddress(?string $remoteAddress): void
    {
        $this->remoteAddress = $remoteAddress;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }
}
