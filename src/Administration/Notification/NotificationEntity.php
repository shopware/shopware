<?php declare(strict_types=1);

namespace Shopware\Administration\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\User\UserEntity;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use Shopware\Core\Framework\Notification\NotificationEntity instead
 */
#[Package('framework')]
class NotificationEntity extends Entity
{
    protected string $id;

    protected ?string $createdByIntegrationId = null;

    protected ?IntegrationEntity $createdByIntegration = null;

    protected ?string $createdByUserId = null;

    protected ?UserEntity $createdByUser = null;

    protected bool $adminOnly;

    /**
     * @var array<string>
     */
    protected array $requiredPrivileges = [];

    protected string $status;

    protected string $message;

    public function getId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->id;
    }

    public function setId(string $id): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->id = $id;
        $this->_uniqueIdentifier = $id;
    }

    public function getCreatedByIntegrationId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->createdByIntegrationId;
    }

    public function setCreatedByIntegrationId(string $createdByIntegrationId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->createdByIntegrationId = $createdByIntegrationId;
    }

    public function getCreatedByIntegration(): ?IntegrationEntity
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->createdByIntegration;
    }

    public function setCreatedByIntegration(IntegrationEntity $createdByIntegration): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->createdByIntegration = $createdByIntegration;
    }

    public function getCreatedByUserId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->createdByUserId;
    }

    public function setCreatedByUserId(string $createdByUserId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->createdByUserId = $createdByUserId;
    }

    public function getCreatedByUser(): ?UserEntity
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->createdByUser;
    }

    public function setCreatedByUser(UserEntity $createdByUser): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->createdByUser = $createdByUser;
    }

    public function isAdminOnly(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->adminOnly;
    }

    public function setAdminOnly(bool $adminOnly): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->adminOnly = $adminOnly;
    }

    /**
     * @return array<string>
     */
    public function getRequiredPrivileges(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->requiredPrivileges;
    }

    /**
     * @param array<string> $requiredPrivileges
     */
    public function setRequiredPrivileges(array $requiredPrivileges): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->requiredPrivileges = $requiredPrivileges;
    }

    public function getStatus(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->status;
    }

    public function setStatus(string $status): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->status = $status;
    }

    public function getMessage(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));

        return $this->message;
    }

    public function setMessage(string $message): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', \Shopware\Core\Framework\Notification\NotificationEntity::class));
        $this->message = $message;
    }
}
