<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

#[Package('services-settings')]
class UserEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $localeId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $avatarId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $username;

    /**
     * @internal
     *
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $password;

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
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $email;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $active;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $admin;

    /**
     * @var AclRoleCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $aclRoles;

    /**
     * @var LocaleEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $locale;

    /**
     * @var MediaEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $avatarMedia;

    /**
     * @var MediaCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $media;

    /**
     * @var UserAccessKeyCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $accessKeys;

    /**
     * @var UserConfigCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $configs;

    /**
     * @var StateMachineHistoryCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $stateMachineHistoryEntries;

    /**
     * @var ImportExportLogCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $importExportLogEntries;

    /**
     * @var UserRecoveryEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $recoveryUser;

    /**
     * @internal
     *
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $storeToken;

    /**
     * @var \DateTimeInterface|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $lastUpdatedPasswordAt;

    /**
     * @var OrderCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $createdOrders;

    /**
     * @var OrderCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $updatedOrders;

    /**
     * @var CustomerCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $createdCustomers;

    /**
     * @var CustomerCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $updatedCustomers;

    protected string $timeZone;

    public function getStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->stateMachineHistoryEntries;
    }

    public function setStateMachineHistoryEntries(StateMachineHistoryCollection $stateMachineHistoryEntries): void
    {
        $this->stateMachineHistoryEntries = $stateMachineHistoryEntries;
    }

    public function getImportExportLogEntries(): ?ImportExportLogCollection
    {
        return $this->importExportLogEntries;
    }

    public function setImportExportLogEntries(ImportExportLogCollection $importExportLogEntries): void
    {
        $this->importExportLogEntries = $importExportLogEntries;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getAvatarId(): ?string
    {
        return $this->avatarId;
    }

    public function setAvatarId(string $avatarId): void
    {
        $this->avatarId = $avatarId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @internal
     */
    public function getPassword(): string
    {
        $this->checkIfPropertyAccessIsAllowed('password');

        return $this->password;
    }

    /**
     * @internal
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLocale(): ?LocaleEntity
    {
        return $this->locale;
    }

    public function setLocale(LocaleEntity $locale): void
    {
        $this->locale = $locale;
    }

    public function getAvatarMedia(): ?MediaEntity
    {
        return $this->avatarMedia;
    }

    public function setAvatarMedia(MediaEntity $avatarMedia): void
    {
        $this->avatarMedia = $avatarMedia;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getAccessKeys(): ?UserAccessKeyCollection
    {
        return $this->accessKeys;
    }

    public function setAccessKeys(UserAccessKeyCollection $accessKeys): void
    {
        $this->accessKeys = $accessKeys;
    }

    public function getConfigs(): ?UserConfigCollection
    {
        return $this->configs;
    }

    public function setConfigs(UserConfigCollection $configs): void
    {
        $this->configs = $configs;
    }

    public function getRecoveryUser(): ?UserRecoveryEntity
    {
        return $this->recoveryUser;
    }

    public function setRecoveryUser(UserRecoveryEntity $recoveryUser): void
    {
        $this->recoveryUser = $recoveryUser;
    }

    /**
     * @internal
     */
    public function getStoreToken(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('storeToken');

        return $this->storeToken;
    }

    /**
     * @internal
     */
    public function setStoreToken(?string $storeToken): void
    {
        $this->storeToken = $storeToken;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }

    public function getAclRoles(): AclRoleCollection
    {
        return $this->aclRoles;
    }

    public function setAclRoles(AclRoleCollection $aclRoles): void
    {
        $this->aclRoles = $aclRoles;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedOrders(): ?OrderCollection
    {
        return $this->createdOrders;
    }

    public function setCreatedOrders(OrderCollection $createdOrders): void
    {
        $this->createdOrders = $createdOrders;
    }

    public function getUpdatedOrders(): ?OrderCollection
    {
        return $this->updatedOrders;
    }

    public function setUpdatedOrders(OrderCollection $updatedOrders): void
    {
        $this->updatedOrders = $updatedOrders;
    }

    public function getCreatedCustomers(): ?CustomerCollection
    {
        return $this->createdCustomers;
    }

    public function setCreatedCustomers(CustomerCollection $createdCustomers): void
    {
        $this->createdCustomers = $createdCustomers;
    }

    public function getUpdatedCustomers(): ?CustomerCollection
    {
        return $this->updatedCustomers;
    }

    public function setUpdatedCustomers(CustomerCollection $updatedCustomers): void
    {
        $this->updatedCustomers = $updatedCustomers;
    }

    public function getLastUpdatedPasswordAt(): ?\DateTimeInterface
    {
        return $this->lastUpdatedPasswordAt;
    }

    public function setLastUpdatedPasswordAt(\DateTimeInterface $lastUpdatedPasswordAt): void
    {
        $this->lastUpdatedPasswordAt = $lastUpdatedPasswordAt;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    public function setTimeZone(string $timeZone): void
    {
        $this->timeZone = $timeZone;
    }
}
