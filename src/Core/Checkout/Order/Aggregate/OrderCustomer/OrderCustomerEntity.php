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

    protected string $email;

    protected string $orderId;

    protected ?string $salutationId = null;

    protected string $firstName;

    protected string $lastName;

    protected ?string $title = null;

    /**
     * @var list<string>|null
     */
    protected ?array $vatIds = null;

    protected ?string $company = null;

    protected ?string $customerNumber = null;

    protected ?string $customerId = null;

    protected ?CustomerEntity $customer = null;

    protected ?SalutationEntity $salutation = null;

    protected ?OrderEntity $order = null;

    protected ?string $remoteAddress = null;

    protected string $orderVersionId;

    protected ?string $displayName = null;

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

    /**
     * @return list<string>|null
     */
    public function getVatIds(): ?array
    {
        return $this->vatIds;
    }

    /**
     * @param list<string> $vatIds
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

    public function getDisplayName(): string
    {
        return $this->displayName ?? self::resolveDisplayName($this->firstName ?? '', $this->lastName ?? '', $this->company);
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public static function resolveDisplayName(string $firstName, string $lastName, ?string $company): string
    {
        $personName = trim($firstName . ' ' . $lastName);

        return $personName !== '' ? $personName : trim($company ?? '');
    }

    public function getBuyerName(): string
    {
        $personName = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
        $company = trim($this->company ?? '');

        return match (true) {
            $company === '' => $personName,
            $personName === '' || $personName === $company => $company,
            default => $personName . ' - ' . $company,
        };
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
