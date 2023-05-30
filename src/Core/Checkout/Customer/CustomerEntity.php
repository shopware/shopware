<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\User\UserEntity;

#[Package('customer-order')]
class CustomerEntity extends Entity implements \Stringable
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    final public const ACCOUNT_TYPE_PRIVATE = 'private';
    final public const ACCOUNT_TYPE_BUSINESS = 'business';

    protected string $groupId;

    protected string $defaultPaymentMethodId;

    protected string $salesChannelId;

    protected string $languageId;

    protected ?string $lastPaymentMethodId = null;

    protected string $defaultBillingAddressId;

    protected string $defaultShippingAddressId;

    protected string $customerNumber;

    protected ?string $salutationId = null;

    protected string $firstName;

    protected string $lastName;

    protected ?string $company = null;

    /**
     * @internal
     */
    protected ?string $password = null;

    protected string $email;

    protected ?string $title = null;

    /**
     * @var array<string>|null
     */
    protected ?array $vatIds = null;

    protected ?string $affiliateCode = null;

    protected ?string $campaignCode = null;

    protected bool $active;

    protected bool $doubleOptInRegistration;

    protected ?\DateTimeInterface $doubleOptInEmailSentDate = null;

    protected ?\DateTimeInterface $doubleOptInConfirmDate = null;

    protected ?string $hash = null;

    protected bool $guest;

    protected ?\DateTimeInterface $firstLogin = null;

    protected ?\DateTimeInterface $lastLogin = null;

    protected string $accountType;

    /**
     * @var array<string>|null
     *
     * @internal
     */
    protected ?array $newsletterSalesChannelIds = null;

    protected ?\DateTimeInterface $birthday = null;

    protected ?\DateTimeInterface $lastOrderDate = null;

    protected int $orderCount;

    protected float $orderTotalAmount;

    protected int $reviewCount;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @internal
     */
    protected ?string $legacyEncoder = null;

    /**
     * @internal
     */
    protected ?string $legacyPassword = null;

    protected ?CustomerGroupEntity $group = null;

    protected ?PaymentMethodEntity $defaultPaymentMethod = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?LanguageEntity $language = null;

    protected ?PaymentMethodEntity $lastPaymentMethod = null;

    protected ?SalutationEntity $salutation = null;

    protected ?CustomerAddressEntity $defaultBillingAddress = null;

    protected ?CustomerAddressEntity $defaultShippingAddress = null;

    protected ?CustomerAddressEntity $activeBillingAddress = null;

    protected ?CustomerAddressEntity $activeShippingAddress = null;

    protected ?CustomerAddressCollection $addresses = null;

    protected ?OrderCustomerCollection $orderCustomers = null;

    protected int $autoIncrement;

    protected ?TagCollection $tags = null;

    /**
     * @var list<string>|null
     */
    protected ?array $tagIds = null;

    protected ?PromotionCollection $promotions = null;

    protected ?CustomerRecoveryEntity $recoveryCustomer = null;

    protected ?ProductReviewCollection $productReviews = null;

    protected ?string $remoteAddress = null;

    protected ?string $requestedGroupId = null;

    protected ?CustomerGroupEntity $requestedGroup = null;

    protected ?string $boundSalesChannelId = null;

    protected ?SalesChannelEntity $boundSalesChannel = null;

    protected ?CustomerWishlistCollection $wishlists = null;

    protected ?string $createdById = null;

    protected ?UserEntity $createdBy = null;

    protected ?string $updatedById = null;

    protected ?UserEntity $updatedBy = null;

    public function __toString(): string
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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    /**
     * @internal
     */
    public function getPassword(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('password');

        return $this->password;
    }

    /**
     * @internal
     */
    public function setPassword(?string $password): void
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

    /**
     * @return array<string>|null
     */
    public function getVatIds(): ?array
    {
        return $this->vatIds;
    }

    /**
     * @param array<string>|null $vatIds
     */
    public function setVatIds(?array $vatIds): void
    {
        $this->vatIds = $vatIds;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getDoubleOptInRegistration(): bool
    {
        return $this->doubleOptInRegistration;
    }

    public function setDoubleOptInRegistration(bool $doubleOptInRegistration): void
    {
        $this->doubleOptInRegistration = $doubleOptInRegistration;
    }

    public function getDoubleOptInEmailSentDate(): ?\DateTimeInterface
    {
        return $this->doubleOptInEmailSentDate;
    }

    public function setDoubleOptInEmailSentDate(\DateTimeInterface $doubleOptInEmailSentDate): void
    {
        $this->doubleOptInEmailSentDate = $doubleOptInEmailSentDate;
    }

    public function getDoubleOptInConfirmDate(): ?\DateTimeInterface
    {
        return $this->doubleOptInConfirmDate;
    }

    public function setDoubleOptInConfirmDate(\DateTimeInterface $doubleOptInConfirmDate): void
    {
        $this->doubleOptInConfirmDate = $doubleOptInConfirmDate;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getGuest(): bool
    {
        return $this->guest;
    }

    public function setGuest(bool $guest): void
    {
        $this->guest = $guest;
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

    /**
     * @internal
     *
     * @return array<string>|null
     */
    public function getNewsletterSalesChannelIds(): ?array
    {
        $this->checkIfPropertyAccessIsAllowed('newsletterSalesChannelIds');

        return $this->newsletterSalesChannelIds;
    }

    /**
     * @internal
     *
     * @param array<string>|null $newsletterSalesChannelIds
     */
    public function setNewsletterSalesChannelIds(?array $newsletterSalesChannelIds): void
    {
        $this->newsletterSalesChannelIds = $newsletterSalesChannelIds;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getLastOrderDate(): ?\DateTimeInterface
    {
        return $this->lastOrderDate;
    }

    public function setLastOrderDate(?\DateTimeInterface $lastOrderDate): void
    {
        $this->lastOrderDate = $lastOrderDate;
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function setOrderCount(int $orderCount): void
    {
        $this->orderCount = $orderCount;
    }

    public function getOrderTotalAmount(): float
    {
        return $this->orderTotalAmount;
    }

    public function getReviewCount(): int
    {
        return $this->reviewCount;
    }

    public function setReviewCount(int $reviewCount): void
    {
        $this->reviewCount = $reviewCount;
    }

    public function setOrderTotalAmount(float $orderTotalAmount): void
    {
        $this->orderTotalAmount = $orderTotalAmount;
    }

    /**
     * @internal
     */
    public function getLegacyEncoder(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('legacyEncoder');

        return $this->legacyEncoder;
    }

    /**
     * @internal
     */
    public function setLegacyEncoder(?string $legacyEncoder): void
    {
        $this->legacyEncoder = $legacyEncoder;
    }

    /**
     * @internal
     */
    public function getLegacyPassword(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('legacyPassword');

        return $this->legacyPassword;
    }

    /**
     * @internal
     */
    public function setLegacyPassword(?string $legacyPassword): void
    {
        $this->legacyPassword = $legacyPassword;
    }

    public function hasLegacyPassword(): bool
    {
        return $this->legacyPassword !== null && $this->legacyEncoder !== null;
    }

    public function getGroup(): ?CustomerGroupEntity
    {
        return $this->group;
    }

    public function setGroup(CustomerGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getDefaultPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->defaultPaymentMethod;
    }

    public function setDefaultPaymentMethod(PaymentMethodEntity $defaultPaymentMethod): void
    {
        $this->defaultPaymentMethod = $defaultPaymentMethod;
    }

    public function getSalesChannel(): ?SalesChannelEntity
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

    public function getSalutation(): ?SalutationEntity
    {
        return $this->salutation;
    }

    public function setSalutation(SalutationEntity $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getDefaultBillingAddress(): ?CustomerAddressEntity
    {
        return $this->defaultBillingAddress;
    }

    public function setDefaultBillingAddress(CustomerAddressEntity $defaultBillingAddress): void
    {
        $this->defaultBillingAddress = $defaultBillingAddress;
    }

    public function getDefaultShippingAddress(): ?CustomerAddressEntity
    {
        return $this->defaultShippingAddress;
    }

    public function setDefaultShippingAddress(CustomerAddressEntity $defaultShippingAddress): void
    {
        $this->defaultShippingAddress = $defaultShippingAddress;
    }

    public function getActiveBillingAddress(): ?CustomerAddressEntity
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

    public function getActiveShippingAddress(): ?CustomerAddressEntity
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

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return list<string>|null
     */
    public function getTagIds(): ?array
    {
        return $this->tagIds;
    }

    /**
     * @param list<string> $tagIds
     */
    public function setTagIds(array $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    /**
     * Gets a list of all promotions where the customer
     * is assigned to within the "persona" conditions.
     */
    public function getPromotions(): ?PromotionCollection
    {
        return $this->promotions;
    }

    /**
     * Sets a list of all promotions where the customer
     * should be assigned to within the "persona" conditions.
     */
    public function setPromotions(PromotionCollection $promotions): void
    {
        $this->promotions = $promotions;
    }

    public function getProductReviews(): ?ProductReviewCollection
    {
        return $this->productReviews;
    }

    public function setProductReviews(ProductReviewCollection $productReviews): void
    {
        $this->productReviews = $productReviews;
    }

    public function getRecoveryCustomer(): ?CustomerRecoveryEntity
    {
        return $this->recoveryCustomer;
    }

    public function setRecoveryCustomer(?CustomerRecoveryEntity $recoveryCustomer): void
    {
        $this->recoveryCustomer = $recoveryCustomer;
    }

    public function getAffiliateCode(): ?string
    {
        return $this->affiliateCode;
    }

    public function setAffiliateCode(?string $affiliateCode): void
    {
        $this->affiliateCode = $affiliateCode;
    }

    public function getCampaignCode(): ?string
    {
        return $this->campaignCode;
    }

    public function setCampaignCode(?string $campaignCode): void
    {
        $this->campaignCode = $campaignCode;
    }

    public function getRemoteAddress(): ?string
    {
        return $this->remoteAddress;
    }

    public function setRemoteAddress(?string $remoteAddress): void
    {
        $this->remoteAddress = $remoteAddress;
    }

    public function getRequestedGroupId(): ?string
    {
        return $this->requestedGroupId;
    }

    public function setRequestedGroupId(?string $requestedGroupId): void
    {
        $this->requestedGroupId = $requestedGroupId;
    }

    public function getRequestedGroup(): ?CustomerGroupEntity
    {
        return $this->requestedGroup;
    }

    public function setRequestedGroup(?CustomerGroupEntity $requestedGroup): void
    {
        $this->requestedGroup = $requestedGroup;
    }

    public function getBoundSalesChannelId(): ?string
    {
        return $this->boundSalesChannelId;
    }

    public function setBoundSalesChannelId(?string $boundSalesChannelId): void
    {
        $this->boundSalesChannelId = $boundSalesChannelId;
    }

    public function getBoundSalesChannel(): ?SalesChannelEntity
    {
        return $this->boundSalesChannel;
    }

    public function setBoundSalesChannel(SalesChannelEntity $boundSalesChannel): void
    {
        $this->boundSalesChannel = $boundSalesChannel;
    }

    public function setAccountType(string $accountType): void
    {
        $this->accountType = $accountType;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getWishlists(): ?CustomerWishlistCollection
    {
        return $this->wishlists;
    }

    public function setWishlists(CustomerWishlistCollection $wishlists): void
    {
        $this->wishlists = $wishlists;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(string $createdById): void
    {
        $this->createdById = $createdById;
    }

    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(UserEntity $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedById(): ?string
    {
        return $this->updatedById;
    }

    public function setUpdatedById(string $updatedById): void
    {
        $this->updatedById = $updatedById;
    }

    public function getUpdatedBy(): ?UserEntity
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(UserEntity $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
