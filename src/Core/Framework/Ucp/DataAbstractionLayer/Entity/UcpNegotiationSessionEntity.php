<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpNegotiationSessionEntity extends Entity
{
    use EntityIdTrait;

    protected string $salesChannelId;

    protected string $platformProfileUri;

    protected string $platformProfileHash;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $activeCapabilities = [];

    protected string $protocolVersion;

    protected \DateTimeImmutable $lastUsedAt;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getPlatformProfileUri(): string
    {
        return $this->platformProfileUri;
    }

    public function setPlatformProfileUri(string $platformProfileUri): void
    {
        $this->platformProfileUri = $platformProfileUri;
    }

    public function getPlatformProfileHash(): string
    {
        return $this->platformProfileHash;
    }

    public function setPlatformProfileHash(string $platformProfileHash): void
    {
        $this->platformProfileHash = $platformProfileHash;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getActiveCapabilities(): array
    {
        return $this->activeCapabilities;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $activeCapabilities
     */
    public function setActiveCapabilities(array $activeCapabilities): void
    {
        $this->activeCapabilities = $activeCapabilities;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function setProtocolVersion(string $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
    }

    public function getLastUsedAt(): \DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(\DateTimeImmutable $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }
}
