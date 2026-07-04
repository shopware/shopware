<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\UserMethod;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserEntity;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
class AdminAuthUserMethodEntity extends Entity
{
    use EntityIdTrait;

    protected string $userId;

    protected string $type;

    protected bool $active = false;

    protected ?string $label = null;

    protected ?string $secret = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $credential = null;

    protected ?\DateTimeInterface $lastUsedAt = null;

    protected ?UserEntity $user = null;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCredential(): ?array
    {
        return $this->credential;
    }

    /**
     * @param array<string, mixed>|null $credential
     */
    public function setCredential(?array $credential): void
    {
        $this->credential = $credential;
    }

    public function getLastUsedAt(): ?\DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeInterface $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
    }
}
