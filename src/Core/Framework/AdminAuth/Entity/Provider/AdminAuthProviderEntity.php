<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\Provider;

use Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity\AdminAuthOauthIdentityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
class AdminAuthProviderEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $type;

    protected bool $active = false;

    protected bool $isPrimary = false;

    protected bool $isSecondFactor = false;

    protected int $priority = 0;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $config = null;

    protected ?AdminAuthOauthIdentityCollection $identities = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getIsPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getIsSecondFactor(): bool
    {
        return $this->isSecondFactor;
    }

    public function setIsSecondFactor(bool $isSecondFactor): void
    {
        $this->isSecondFactor = $isSecondFactor;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getIdentities(): ?AdminAuthOauthIdentityCollection
    {
        return $this->identities;
    }

    public function setIdentities(AdminAuthOauthIdentityCollection $identities): void
    {
        $this->identities = $identities;
    }
}
