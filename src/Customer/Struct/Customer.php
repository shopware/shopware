<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Customer\Struct;

use Shopware\Address\Struct\Address;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\Framework\Struct\Struct;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\Shop\Struct\Shop;

class Customer extends Struct
{
    const ACCOUNT_MODE_CUSTOMER = 0;
    const ACCOUNT_MODE_FAST_LOGIN = 1;

    const CUSTOMER_TYPE_PRIVATE = 'private';
    const CUSTOMER_TYPE_BUSINESS = 'business';

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $number;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $salutation;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string
     */
    protected $firstname;

    /**
     * @var string
     */
    protected $lastname;

    /**
     * @var \Shopware\Shop\Struct\Shop
     */
    protected $assignedShop;

    /**
     * @var \Shopware\Shop\Struct\Shop
     */
    protected $assignedLanguageShop;

    /**
     * @var int|null
     */
    protected $assignedShopId;

    /**
     * @var int|null
     */
    protected $assignedLanguageShopId;

    /**
     * @var \DateTime
     */
    protected $firstLogin;

    /**
     * @var \DateTime
     */
    protected $lastLogin;

    /**
     * @var \DateTime|null
     */
    protected $lockedUntil;

    /**
     * @var int
     */
    protected $failedLogins;

    /**
     * @var \DateTime|null
     */
    protected $birthday;

    /**
     * @var int
     */
    protected $accountMode = self::ACCOUNT_MODE_CUSTOMER;

    /**
     * @var string
     */
    protected $customerType = self::CUSTOMER_TYPE_PRIVATE;

    /**
     * @var string|null
     */
    protected $validation;

    /**
     * @var string|null
     */
    protected $confirmationKey;

    /**
     * @var bool
     */
    protected $orderedNewsletter = false;

    /**
     * @var bool
     */
    protected $isPartner = false;

    /**
     * @var string|null
     */
    protected $referer;

    /**
     * @var string
     */
    protected $internalComment;

    /**
     * @var CustomerGroup
     */
    protected $customerGroup;

    /**
     * @var bool
     */
    protected $hasNotifications;

    /**
     * @var int
     */
    protected $defaultBillingAddressId;

    /**
     * @var int
     */
    protected $defaultShippingAddressId;

    /**
     * @var \Shopware\Address\Struct\Address
     */
    protected $defaultBillingAddress;

    /**
     * @var Address
     */
    protected $defaultShippingAddress;

    /**
     * @var int
     */
    protected $presetPaymentMethodId;

    /**
     * @var int
     */
    protected $lastPaymentMethodId;

    /**
     * @var \Shopware\PaymentMethod\Struct\PaymentMethod
     */
    protected $presetPaymentMethod;

    /**
     * @var \Shopware\PaymentMethod\Struct\PaymentMethod
     */
    protected $lastPaymentMethod;

    /**
     * @var Address|null
     */
    protected $activeBillingAddress;

    /**
     * @var Address|null
     */
    protected $activeShippingAddress;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getAssignedShop(): Shop
    {
        return $this->assignedShop;
    }

    public function setAssignedShop(Shop $assignedShop): void
    {
        $this->assignedShop = $assignedShop;
    }

    public function getAssignedLanguageShop(): Shop
    {
        return $this->assignedLanguageShop;
    }

    public function setAssignedLanguageShop(Shop $assignedLanguageShop): void
    {
        $this->assignedLanguageShop = $assignedLanguageShop;
    }

    public function getAssignedShopId()
    {
        return $this->assignedShopId;
    }

    public function setAssignedShopId($assignedShopId): void
    {
        $this->assignedShopId = $assignedShopId;
    }

    public function getAssignedLanguageShopId()
    {
        return $this->assignedLanguageShopId;
    }

    public function setAssignedLanguageShopId($assignedLanguageShopId): void
    {
        $this->assignedLanguageShopId = $assignedLanguageShopId;
    }

    public function getFirstLogin(): \DateTime
    {
        return $this->firstLogin;
    }

    public function setFirstLogin(\DateTime $firstLogin): void
    {
        $this->firstLogin = $firstLogin;
    }

    public function getLastLogin(): \DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(\DateTime $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getLockedUntil()
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil($lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
    }

    public function getFailedLogins(): int
    {
        return $this->failedLogins;
    }

    public function setFailedLogins(int $failedLogins): void
    {
        $this->failedLogins = $failedLogins;
    }

    public function getBirthday()
    {
        return $this->birthday;
    }

    public function setBirthday($birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getAccountMode(): int
    {
        return $this->accountMode;
    }

    public function setAccountMode(int $accountMode): void
    {
        $this->accountMode = $accountMode;
    }

    public function getCustomerType(): string
    {
        return $this->customerType;
    }

    public function setCustomerType(string $customerType): void
    {
        $this->customerType = $customerType;
    }

    public function getValidation()
    {
        return $this->validation;
    }

    public function setValidation($validation): void
    {
        $this->validation = $validation;
    }

    public function getConfirmationKey()
    {
        return $this->confirmationKey;
    }

    public function setConfirmationKey($confirmationKey): void
    {
        $this->confirmationKey = $confirmationKey;
    }

    public function isOrderedNewsletter(): bool
    {
        return $this->orderedNewsletter;
    }

    public function setOrderedNewsletter(bool $orderedNewsletter): void
    {
        $this->orderedNewsletter = $orderedNewsletter;
    }

    public function isIsPartner(): bool
    {
        return $this->isPartner;
    }

    public function setIsPartner(bool $isPartner): void
    {
        $this->isPartner = $isPartner;
    }

    public function getReferer()
    {
        return $this->referer;
    }

    public function setReferer($referer): void
    {
        $this->referer = $referer;
    }

    public function getInternalComment(): string
    {
        return $this->internalComment;
    }

    public function setInternalComment(string $internalComment): void
    {
        $this->internalComment = $internalComment;
    }

    public function getCustomerGroup(): CustomerGroup
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroup $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function isHasNotifications(): bool
    {
        return $this->hasNotifications;
    }

    public function setHasNotifications(bool $hasNotifications): void
    {
        $this->hasNotifications = $hasNotifications;
    }

    public function getDefaultBillingAddressId(): int
    {
        return $this->defaultBillingAddressId;
    }

    public function setDefaultBillingAddressId(int $defaultBillingAddressId): void
    {
        $this->defaultBillingAddressId = $defaultBillingAddressId;
    }

    public function getDefaultShippingAddressId(): int
    {
        return $this->defaultShippingAddressId;
    }

    public function setDefaultShippingAddressId(int $defaultShippingAddressId): void
    {
        $this->defaultShippingAddressId = $defaultShippingAddressId;
    }

    public function getDefaultBillingAddress(): Address
    {
        return $this->defaultBillingAddress;
    }

    public function setDefaultBillingAddress(Address $defaultBillingAddress): void
    {
        $this->defaultBillingAddress = $defaultBillingAddress;
    }

    public function getDefaultShippingAddress(): Address
    {
        return $this->defaultShippingAddress;
    }

    public function setDefaultShippingAddress(Address $defaultShippingAddress): void
    {
        $this->defaultShippingAddress = $defaultShippingAddress;
    }

    public function getPresetPaymentMethodId(): int
    {
        return $this->presetPaymentMethodId;
    }

    public function setPresetPaymentMethodId(int $presetPaymentMethodId): void
    {
        $this->presetPaymentMethodId = $presetPaymentMethodId;
    }

    public function getLastPaymentMethodId(): int
    {
        return $this->lastPaymentMethodId;
    }

    public function setLastPaymentMethodId(int $lastPaymentMethodId): void
    {
        $this->lastPaymentMethodId = $lastPaymentMethodId;
    }

    public function getPresetPaymentMethod(): PaymentMethod
    {
        return $this->presetPaymentMethod;
    }

    public function setPresetPaymentMethod(PaymentMethod $presetPaymentMethod): void
    {
        $this->presetPaymentMethod = $presetPaymentMethod;
    }

    public function getLastPaymentMethod(): PaymentMethod
    {
        return $this->lastPaymentMethod;
    }

    public function setLastPaymentMethod(PaymentMethod $lastPaymentMethod): void
    {
        $this->lastPaymentMethod = $lastPaymentMethod;
    }

    public function getActiveBillingAddress(): Address
    {
        if (!$this->activeBillingAddress) {
            return $this->defaultBillingAddress;
        }

        return $this->activeBillingAddress;
    }

    public function setActiveBillingAddress(Address $activeBillingAddress): void
    {
        $this->activeBillingAddress = $activeBillingAddress;
    }

    public function getActiveShippingAddress(): Address
    {
        if (!$this->activeShippingAddress) {
            return $this->defaultShippingAddress;
        }

        return $this->activeShippingAddress;
    }

    public function setActiveShippingAddress(Address $activeShippingAddress): void
    {
        $this->activeShippingAddress = $activeShippingAddress;
    }

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);

        $data['activeShippingAddress'] = $this->getActiveShippingAddress();
        $data['activeBillingAddress'] = $this->getActiveBillingAddress();

        return $data;
    }
}
