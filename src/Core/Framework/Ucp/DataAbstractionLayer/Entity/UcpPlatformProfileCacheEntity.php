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
class UcpPlatformProfileCacheEntity extends Entity
{
    use EntityIdTrait;

    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_PENDING = 'pending';

    protected string $profileUri;

    protected string $profileUriHash;

    /**
     * @var array<string, mixed>
     */
    protected array $profileJson;

    protected ?string $etag = null;

    protected \DateTimeImmutable $fetchedAt;

    protected \DateTimeImmutable $expiresAt;

    protected string $verificationStatus;

    protected int $failureCount = 0;

    public function getProfileUri(): string
    {
        return $this->profileUri;
    }

    public function setProfileUri(string $profileUri): void
    {
        $this->profileUri = $profileUri;
    }

    public function getProfileUriHash(): string
    {
        return $this->profileUriHash;
    }

    public function setProfileUriHash(string $profileUriHash): void
    {
        $this->profileUriHash = $profileUriHash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfileJson(): array
    {
        return $this->profileJson;
    }

    /**
     * @param array<string, mixed> $profileJson
     */
    public function setProfileJson(array $profileJson): void
    {
        $this->profileJson = $profileJson;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function setEtag(?string $etag): void
    {
        $this->etag = $etag;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTimeImmutable $fetchedAt): void
    {
        $this->fetchedAt = $fetchedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getVerificationStatus(): string
    {
        return $this->verificationStatus;
    }

    public function setVerificationStatus(string $verificationStatus): void
    {
        $this->verificationStatus = $verificationStatus;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function setFailureCount(int $failureCount): void
    {
        $this->failureCount = $failureCount;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->expiresAt < $now;
    }
}
