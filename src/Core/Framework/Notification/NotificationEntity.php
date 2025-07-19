<?php declare(strict_types=1); // @phpstan-ignore symplify.multipleClassLikeInFile

namespace Shopware\Core\Framework\Notification;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\User\UserEntity;

if (class_exists(\Shopware\Administration\Notification\NotificationEntity::class)) {
    /**
     * @deprecated tag:v6.8.0 - reason:class-hierarchy-change - Will not extend from `\Shopware\Administration\Notification\NotificationEntity` and will instead extend directly from `\Shopware\Core\Framework\DataAbstractionLayer\Entity`
     */
    #[Package('framework')]
    class NotificationEntity extends \Shopware\Administration\Notification\NotificationEntity
    {
        use EntityIdTrait;

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

        public function getCreatedByIntegrationId(): ?string
        {
            return $this->createdByIntegrationId;
        }

        public function setCreatedByIntegrationId(string $createdByIntegrationId): void
        {
            $this->createdByIntegrationId = $createdByIntegrationId;
        }

        public function getCreatedByIntegration(): ?IntegrationEntity
        {
            return $this->createdByIntegration;
        }

        public function setCreatedByIntegration(IntegrationEntity $createdByIntegration): void
        {
            $this->createdByIntegration = $createdByIntegration;
        }

        public function getCreatedByUserId(): ?string
        {
            return $this->createdByUserId;
        }

        public function setCreatedByUserId(string $createdByUserId): void
        {
            $this->createdByUserId = $createdByUserId;
        }

        public function getCreatedByUser(): ?UserEntity
        {
            return $this->createdByUser;
        }

        public function setCreatedByUser(UserEntity $createdByUser): void
        {
            $this->createdByUser = $createdByUser;
        }

        public function isAdminOnly(): bool
        {
            return $this->adminOnly;
        }

        public function setAdminOnly(bool $adminOnly): void
        {
            $this->adminOnly = $adminOnly;
        }

        /**
         * @return array<string>
         */
        public function getRequiredPrivileges(): array
        {
            return $this->requiredPrivileges;
        }

        /**
         * @param array<string> $requiredPrivileges
         */
        public function setRequiredPrivileges(array $requiredPrivileges): void
        {
            $this->requiredPrivileges = $requiredPrivileges;
        }

        public function getStatus(): string
        {
            return $this->status;
        }

        public function setStatus(string $status): void
        {
            $this->status = $status;
        }

        public function getMessage(): string
        {
            return $this->message;
        }

        public function setMessage(string $message): void
        {
            $this->message = $message;
        }
    }
} else {
    #[Package('framework')]
    class NotificationEntity extends Entity
    {
        use EntityIdTrait;

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

        public function getCreatedByIntegrationId(): ?string
        {
            return $this->createdByIntegrationId;
        }

        public function setCreatedByIntegrationId(string $createdByIntegrationId): void
        {
            $this->createdByIntegrationId = $createdByIntegrationId;
        }

        public function getCreatedByIntegration(): ?IntegrationEntity
        {
            return $this->createdByIntegration;
        }

        public function setCreatedByIntegration(IntegrationEntity $createdByIntegration): void
        {
            $this->createdByIntegration = $createdByIntegration;
        }

        public function getCreatedByUserId(): ?string
        {
            return $this->createdByUserId;
        }

        public function setCreatedByUserId(string $createdByUserId): void
        {
            $this->createdByUserId = $createdByUserId;
        }

        public function getCreatedByUser(): ?UserEntity
        {
            return $this->createdByUser;
        }

        public function setCreatedByUser(UserEntity $createdByUser): void
        {
            $this->createdByUser = $createdByUser;
        }

        public function isAdminOnly(): bool
        {
            return $this->adminOnly;
        }

        public function setAdminOnly(bool $adminOnly): void
        {
            $this->adminOnly = $adminOnly;
        }

        /**
         * @return array<string>
         */
        public function getRequiredPrivileges(): array
        {
            return $this->requiredPrivileges;
        }

        /**
         * @param array<string> $requiredPrivileges
         */
        public function setRequiredPrivileges(array $requiredPrivileges): void
        {
            $this->requiredPrivileges = $requiredPrivileges;
        }

        public function getStatus(): string
        {
            return $this->status;
        }

        public function setStatus(string $status): void
        {
            $this->status = $status;
        }

        public function getMessage(): string
        {
            return $this->message;
        }

        public function setMessage(string $message): void
        {
            $this->message = $message;
        }
    }
}
