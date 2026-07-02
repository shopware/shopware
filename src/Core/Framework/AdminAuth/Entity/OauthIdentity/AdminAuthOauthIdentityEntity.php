<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity;

use Shopware\Core\Framework\AdminAuth\Entity\Provider\AdminAuthProviderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserEntity;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
class AdminAuthOauthIdentityEntity extends Entity
{
    use EntityIdTrait;

    protected string $providerId;

    protected string $userId;

    protected string $sub;

    protected ?string $email = null;

    protected ?AdminAuthProviderEntity $provider = null;

    protected ?UserEntity $user = null;

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function setProviderId(string $providerId): void
    {
        $this->providerId = $providerId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getSub(): string
    {
        return $this->sub;
    }

    public function setSub(string $sub): void
    {
        $this->sub = $sub;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getProvider(): ?AdminAuthProviderEntity
    {
        return $this->provider;
    }

    public function setProvider(?AdminAuthProviderEntity $provider): void
    {
        $this->provider = $provider;
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
