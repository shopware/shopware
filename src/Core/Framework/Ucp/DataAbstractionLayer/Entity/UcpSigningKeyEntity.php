<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpSigningKeyEntity extends Entity
{
    use EntityIdTrait;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRING = 'retiring';
    public const STATUS_RETIRED = 'retired';

    public const ALGORITHM_ES256 = 'ES256';
    public const ALGORITHM_ES384 = 'ES384';

    protected string $salesChannelId;

    protected string $kid;

    protected string $algorithm;

    /**
     * @var array<string, mixed>
     */
    protected array $publicJwk;

    protected string $privateKeyPemEncrypted;

    protected string $status;

    protected ?\DateTimeImmutable $activatedAt = null;

    protected ?\DateTimeImmutable $retiringAt = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getKid(): string
    {
        return $this->kid;
    }

    public function setKid(string $kid): void
    {
        $this->kid = $kid;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicJwk(): array
    {
        return $this->publicJwk;
    }

    /**
     * @param array<string, mixed> $publicJwk
     */
    public function setPublicJwk(array $publicJwk): void
    {
        $this->publicJwk = $publicJwk;
    }

    public function getPrivateKeyPemEncrypted(): string
    {
        return $this->privateKeyPemEncrypted;
    }

    public function setPrivateKeyPemEncrypted(string $privateKeyPemEncrypted): void
    {
        $this->privateKeyPemEncrypted = $privateKeyPemEncrypted;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(?\DateTimeImmutable $activatedAt): void
    {
        $this->activatedAt = $activatedAt;
    }

    public function getRetiringAt(): ?\DateTimeImmutable
    {
        return $this->retiringAt;
    }

    public function setRetiringAt(?\DateTimeImmutable $retiringAt): void
    {
        $this->retiringAt = $retiringAt;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
