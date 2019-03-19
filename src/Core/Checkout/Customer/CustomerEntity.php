<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\Framework\Tag\TagCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CustomerEntity extends Entity
{
    use EntityIdTrait;

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
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $languageId;

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
    protected $customerNumber;

    /**
     * @var string|null
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
     * @var string|null
     */
    protected $company;

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
     * @var bool
     */
    protected $guest;

    /**
     * @var string|null
     */
    protected $confirmationKey;

    /**
     * @var \DateTimeInterface|null
     */
    protected $firstLogin;

    /**
     * @var \DateTimeInterface|null
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
     * @var \DateTimeInterface|null
     */
    protected $lockedUntil;

    /**
     * @var \DateTimeInterface|null
     */
    protected $birthday;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var CustomerGroupEntity
     */
    protected $group;

    /**
     * @var PaymentMethodEntity
     */
    protected $defaultPaymentMethod;

    /**
     * @var SalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    /**
     * @var PaymentMethodEntity|null
     */
    protected $lastPaymentMethod;

    /**
     * @var CustomerAddressEntity
     */
    protected $defaultBillingAddress;

    /**
     * @var CustomerAddressEntity
     */
    protected $defaultShippingAddress;

    /**
     * @var CustomerAddressEntity|null
     */
    protected $activeBillingAddress;

    /**
     * @var CustomerAddressEntity|null
     */
    protected $activeShippingAddress;

    /**
     * @var CustomerAddressCollection|null
     */
    protected $addresses;

    /**
     * @var OrderCustomerCollection|null
     */
    protected $orderCustomers;

    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var SearchDocumentCollection|null
     */
    protected $searchKeywords;

    /**
     * @var TagCollection|null
     */
    protected $tags;

    /**
     * @var array|null
     */
    protected $attributes;

    public function __toString()
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

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

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
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

    public function getCustomerNumber(): string
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(string $customerNumber): void
    {
        $this->customerNumber = $customerNumber;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function setSalutation(?string $salutation): void
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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
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

    public function getGuest(): bool
    {
        return $this->guest;
    }

    public function setGuest(bool $guest): void
    {
        $this->guest = $guest;
    }

    public function getConfirmationKey(): ?string
    {
        return $this->confirmationKey;
    }

    public function setConfirmationKey(?string $confirmationKey): void
    {
        $this->confirmationKey = $confirmationKey;
    }

    public function getFirstLogin(): ?\DateTimeInterface
    {
        return $this->firstLogin;
    }

    public function setFirstLogin(?\DateTimeInterface $firstLogin): void
    {
        $this->firstLogin = $firstLogin;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): void
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

    public function getLockedUntil(): ?\DateTimeInterface
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeInterface $lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getGroup(): CustomerGroupEntity
    {
        return $this->group;
    }

    public function setGroup(CustomerGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getDefaultPaymentMethod(): PaymentMethodEntity
    {
        return $this->defaultPaymentMethod;
    }

    public function setDefaultPaymentMethod(PaymentMethodEntity $defaultPaymentMethod): void
    {
        $this->defaultPaymentMethod = $defaultPaymentMethod;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getLastPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->lastPaymentMethod;
    }

    public function setLastPaymentMethod(PaymentMethodEntity $lastPaymentMethod): void
    {
        $this->lastPaymentMethod = $lastPaymentMethod;
    }

    public function getDefaultBillingAddress(): CustomerAddressEntity
    {
        return $this->defaultBillingAddress;
    }

    public function setDefaultBillingAddress(
        CustomerAddressEntity $defaultBillingAddress): void
    {
        $this->defaultBillingAddress = $defaultBillingAddress;
    }

    public function getDefaultShippingAddress(): CustomerAddressEntity
    {
        return $this->defaultShippingAddress;
    }

    public function setDefaultShippingAddress(CustomerAddressEntity $defaultShippingAddress): void
    {
        $this->defaultShippingAddress = $defaultShippingAddress;
    }

    public function getActiveBillingAddress(): CustomerAddressEntity
    {
        if (!$this->activeBillingAddress) {
            return $this->defaultBillingAddress;
        }

        return $this->activeBillingAddress;
    }

    public function setActiveBillingAddress(CustomerAddressEntity $activeBillingAddress): void
    {
        $this->activeBillingAddress = $activeBillingAddress;
    }

    public function getActiveShippingAddress(): CustomerAddressEntity
    {
        if (!$this->activeShippingAddress) {
            return $this->defaultShippingAddress;
        }

        return $this->activeShippingAddress;
    }

    public function setActiveShippingAddress(CustomerAddressEntity $activeShippingAddress): void
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

    public function getOrderCustomers(): ?OrderCustomerCollection
    {
        return $this->orderCustomers;
    }

    public function setOrderCustomers(OrderCustomerCollection $orderCustomers): void
    {
        $this->orderCustomers = $orderCustomers;
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

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }
}
