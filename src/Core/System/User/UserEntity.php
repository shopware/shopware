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

#[Package('system-settings')]
class UserEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string|null
     */
    protected $avatarId;

    /**
     * @var string
     */
    protected $username;

    /**
     * @internal
     *
     * @var string
     */
    protected $password;

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
    protected $title;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $admin;

    /**
     * @var AclRoleCollection
     */
    protected $aclRoles;

    /**
     * @var LocaleEntity|null
     */
    protected $locale;

    /**
     * @var MediaEntity|null
     */
    protected $avatarMedia;

    /**
     * @var MediaCollection|null
     */
    protected $media;

    /**
     * @var UserAccessKeyCollection|null
     */
    protected $accessKeys;

    /**
     * @var UserConfigCollection|null
     */
    protected $configs;

    /**
     * @var StateMachineHistoryCollection|null
     */
    protected $stateMachineHistoryEntries;

    /**
     * @var ImportExportLogCollection|null
     */
    protected $importExportLogEntries;

    /**
     * @var UserRecoveryEntity|null
     */
    protected $recoveryUser;

    /**
     * @internal
     *
     * @var string|null
     */
    protected $storeToken;

    /**
     * @var \DateTimeInterface|null
     */
    protected $lastUpdatedPasswordAt;

    /**
     * @var OrderCollection|null
     */
    protected $createdOrders;

    /**
     * @var OrderCollection|null
     */
    protected $updatedOrders;

    /**
     * @var CustomerCollection|null
     */
    protected $createdCustomers;

    /**
     * @var CustomerCollection|null
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
