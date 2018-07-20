<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupStruct;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\System\Touchpoint\TouchpointStruct;

class CustomerStruct extends Entity
{
    /**
     * @var string
     */
    protected $groupId;

    /**
     * @var string
     */
    protected $defaultPaymentMethodId;

    /**
     * @var string
     */
    protected $touchpointId;

    /**
     * @var string|null
     */
    protected $lastPaymentMethodId;

    /**
     * @var string
     */
    protected $defaultBillingAddressId;

    /**
     * @var string
     */
    protected $defaultShippingAddressId;

    /**
     * @var string
     */
    protected $number;

    /**
     * @var string
     */
    protected $salutation;

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string
     */
    protected $lastName;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string
     */
    protected $encoder;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $accountMode;

    /**
     * @var string|null
     */
    protected $confirmationKey;

    /**
     * @var \DateTime|null
     */
    protected $firstLogin;

    /**
     * @var \DateTime|null
     */
    protected $lastLogin;

    /**
     * @var string|null
     */
    protected $sessionId;

    /**
     * @var bool
     */
    protected $newsletter;

    /**
     * @var string|null
     */
    protected $validation;

    /**
     * @var bool|null
     */
    protected $affiliate;

    /**
     * @var string|null
     */
    protected $referer;

    /**
     * @var string|null
     */
    protected $internalComment;

    /**
     * @var int
     */
    protected $failedLogins;

    /**
     * @var \DateTime|null
     */
    protected $lockedUntil;

    /**
     * @var \DateTime|null
     */
    protected $birthday;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CustomerGroupStruct
     */
    protected $group;

    /**
     * @var PaymentMethodStruct
     */
    protected $defaultPaymentMethod;

    /**
     * @var TouchpointStruct
     */
    protected $touchpoint;

    /**
     * @var PaymentMethodStruct|null
     */
    protected $lastPaymentMethod;

    /**
     * @var CustomerAddressStruct
     */
    protected $defaultBillingAddress;

    /**
     * @var CustomerAddressStruct
     */
    protected $defaultShippingAddress;

    /**
     * @var CustomerAddressStruct|null
     */
    protected $activeBillingAddress;

    /**
     * @var CustomerAddressStruct|null
     */
    protected $activeShippingAddress;

    /**
     * @var CustomerAddressCollection|null
     */
    protected $addresses;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var SearchDocumentCollection|null
     */
    protected $searchKeywords;

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getDefaultPaymentMethodId(): string
    {
        return $this->defaultPaymentMethodId;
    }

    public function setDefaultPaymentMethodId(string $defaultPaymentMethodId): void
    {
        $this->defaultPaymentMethodId = $defaultPaymentMethodId;
    }

    public function getTouchpointId(): string
    {
        return $this->touchpointId;
    }

    public function setTouchpointId(string $touchpointId): void
    {
        $this->touchpointId = $touchpointId;
    }

    public function getLastPaymentMethodId(): ?string
    {
        return $this->lastPaymentMethodId;
    }

    public function setLastPaymentMethodId(?string $lastPaymentMethodId): void
    {
        $this->lastPaymentMethodId = $lastPaymentMethodId;
    }

    public function getDefaultBillingAddressId(): string
    {
        return $this->defaultBillingAddressId;
    }

    public function setDefaultBillingAddressId(string $defaultBillingAddressId): void
    {
        $this->defaultBillingAddressId = $defaultBillingAddressId;
    }

    public function getDefaultShippingAddressId(): string
    {
        return $this->defaultShippingAddressId;
    }

    public function setDefaultShippingAddressId(string $defaultShippingAddressId): void
    {
        $this->defaultShippingAddressId = $defaultShippingAddressId;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = $salutation;
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getEncoder(): string
    {
        return $this->encoder;
    }

    public function setEncoder(string $encoder): void
    {
        $this->encoder = $encoder;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getAccountMode(): int
    {
        return $this->accountMode;
    }

    public function setAccountMode(int $accountMode): void
    {
        $this->accountMode = $accountMode;
    }

    public function getConfirmationKey(): ?string
    {
        return $this->confirmationKey;
    }

    public function setConfirmationKey(?string $confirmationKey): void
    {
        $this->confirmationKey = $confirmationKey;
    }

    public function getFirstLogin(): ?\DateTime
    {
        return $this->firstLogin;
    }

    public function setFirstLogin(?\DateTime $firstLogin): void
    {
        $this->firstLogin = $firstLogin;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTime $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getNewsletter(): bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(bool $newsletter): void
    {
        $this->newsletter = $newsletter;
    }

    public function getValidation(): ?string
    {
        return $this->validation;
    }

    public function setValidation(?string $validation): void
    {
        $this->validation = $validation;
    }

    public function getAffiliate(): ?bool
    {
        return $this->affiliate;
    }

    public function setAffiliate(?bool $affiliate): void
    {
        $this->affiliate = $affiliate;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): void
    {
        $this->referer = $referer;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function setInternalComment(?string $internalComment): void
    {
        $this->internalComment = $internalComment;
    }

    public function getFailedLogins(): int
    {
        return $this->failedLogins;
    }

    public function setFailedLogins(int $failedLogins): void
    {
        $this->failedLogins = $failedLogins;
    }

    public function getLockedUntil(): ?\DateTime
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTime $lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTime $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getGroup(): CustomerGroupStruct
    {
        return $this->group;
    }

    public function setGroup(CustomerGroupStruct $group): void
    {
        $this->group = $group;
    }

    public function getDefaultPaymentMethod(): PaymentMethodStruct
    {
        return $this->defaultPaymentMethod;
    }

    public function setDefaultPaymentMethod(PaymentMethodStruct $defaultPaymentMethod): void
    {
        $this->defaultPaymentMethod = $defaultPaymentMethod;
    }

    public function getTouchpoint(): TouchpointStruct
    {
        return $this->touchpoint;
    }

    public function setTouchpoint(TouchpointStruct $touchpoint): void
    {
        $this->touchpoint = $touchpoint;
    }

    public function getLastPaymentMethod(): ?PaymentMethodStruct
    {
        return $this->lastPaymentMethod;
    }

    public function setLastPaymentMethod(PaymentMethodStruct $lastPaymentMethod): void
    {
        $this->lastPaymentMethod = $lastPaymentMethod;
    }

    public function getDefaultBillingAddress(): CustomerAddressStruct
    {
        return $this->defaultBillingAddress;
    }

    public function setDefaultBillingAddress(
        CustomerAddressStruct $defaultBillingAddress): void
    {
        $this->defaultBillingAddress = $defaultBillingAddress;
    }

    public function getDefaultShippingAddress(): CustomerAddressStruct
    {
        return $this->defaultShippingAddress;
    }

    public function setDefaultShippingAddress(CustomerAddressStruct $defaultShippingAddress): void
    {
        $this->defaultShippingAddress = $defaultShippingAddress;
    }

    public function getActiveBillingAddress(): CustomerAddressStruct
    {
        if (!$this->activeBillingAddress) {
            return $this->defaultBillingAddress;
        }

        return $this->activeBillingAddress;
    }

    public function setActiveBillingAddress(CustomerAddressStruct $activeBillingAddress): void
    {
        $this->activeBillingAddress = $activeBillingAddress;
    }

    public function getActiveShippingAddress(): CustomerAddressStruct
    {
        if (!$this->activeShippingAddress) {
            return $this->defaultShippingAddress;
        }

        return $this->activeShippingAddress;
    }

    public function setActiveShippingAddress(CustomerAddressStruct $activeShippingAddress): void
    {
        $this->activeShippingAddress = $activeShippingAddress;
    }

    public function getAddresses(): ?CustomerAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(CustomerAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getSearchKeywords(): ?SearchDocumentCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(?SearchDocumentCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }
}
