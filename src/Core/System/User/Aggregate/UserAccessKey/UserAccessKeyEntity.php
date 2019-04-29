<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class UserAccessKeyEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var string
     */
    protected $secretAccessKey;

    /**
     * @var bool
     */
    protected $writeAccess;

    /**
     * @var \DateTimeInterface|null
     */
    protected $lastUsageAt;

    /**
     * @var UserEntity|null
     */
    protected $user;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function setSecretAccessKey(string $secretAccessKey): void
    {
        $this->secretAccessKey = $secretAccessKey;
    }

    public function getWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    public function setWriteAccess(bool $writeAccess): void
    {
        $this->writeAccess = $writeAccess;
    }

    public function getLastUsageAt(): ?\DateTimeInterface
    {
        return $this->lastUsageAt;
    }

    public function setLastUsageAt(\DateTimeInterface $lastUsageAt): void
    {
        $this->lastUsageAt = $lastUsageAt;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
