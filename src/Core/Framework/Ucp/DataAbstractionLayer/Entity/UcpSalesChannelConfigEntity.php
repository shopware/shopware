<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpSalesChannelConfigEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public const STRATEGY_DOMAIN = 'domain';
    public const STRATEGY_CONFIG = 'config';

    /**
     * No inbound signature verification (development only).
     */
    public const SIGNATURE_POLICY_OFF = 'off';
    /**
     * Verify; log failures but still accept the request.
     */
    public const SIGNATURE_POLICY_LOG = 'log';
    /**
     * Verify; reject the request on any failure (production default).
     */
    public const SIGNATURE_POLICY_STRICT = 'strict';

    protected string $salesChannelId;

    protected bool $active = false;

    protected string $ucpVersion;

    protected string $profileUriStrategy = self::STRATEGY_DOMAIN;

    protected ?string $customProfileUri = null;

    protected string $signaturePolicy = self::SIGNATURE_POLICY_STRICT;

    protected bool $idempotencyRequired = true;

    /**
     * @var list<string>
     */
    protected array $enabledCapabilities = [];

    /**
     * @var list<string>
     */
    protected array $enabledTransports = [];

    protected ?string $continueUrlTemplate = null;

    /**
     * @var list<string>|null
     */
    protected ?array $platformAllowlist = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $discoveryBudget = null;

    protected ?string $webhookUrlOverride = null;

    protected ?SalesChannelEntity $salesChannel = null;

    /**
     * @var UcpSigningKeyEntity[]|null
     */
    protected ?array $signingKeys = null;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getUcpVersion(): string
    {
        return $this->ucpVersion;
    }

    public function setUcpVersion(string $ucpVersion): void
    {
        $this->ucpVersion = $ucpVersion;
    }

    public function getProfileUriStrategy(): string
    {
        return $this->profileUriStrategy;
    }

    public function setProfileUriStrategy(string $profileUriStrategy): void
    {
        $this->profileUriStrategy = $profileUriStrategy;
    }

    public function getCustomProfileUri(): ?string
    {
        return $this->customProfileUri;
    }

    public function setCustomProfileUri(?string $customProfileUri): void
    {
        $this->customProfileUri = $customProfileUri;
    }

    /**
     * @return list<string>
     */
    public function getEnabledCapabilities(): array
    {
        return $this->enabledCapabilities;
    }

    /**
     * @param list<string> $enabledCapabilities
     */
    public function setEnabledCapabilities(array $enabledCapabilities): void
    {
        $this->enabledCapabilities = $enabledCapabilities;
    }

    /**
     * @return list<string>
     */
    public function getEnabledTransports(): array
    {
        return $this->enabledTransports;
    }

    /**
     * @param list<string> $enabledTransports
     */
    public function setEnabledTransports(array $enabledTransports): void
    {
        $this->enabledTransports = $enabledTransports;
    }

    public function getContinueUrlTemplate(): ?string
    {
        return $this->continueUrlTemplate;
    }

    public function setContinueUrlTemplate(?string $continueUrlTemplate): void
    {
        $this->continueUrlTemplate = $continueUrlTemplate;
    }

    /**
     * @return list<string>|null
     */
    public function getPlatformAllowlist(): ?array
    {
        return $this->platformAllowlist;
    }

    /**
     * @param list<string>|null $platformAllowlist
     */
    public function setPlatformAllowlist(?array $platformAllowlist): void
    {
        $this->platformAllowlist = $platformAllowlist;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDiscoveryBudget(): ?array
    {
        return $this->discoveryBudget;
    }

    /**
     * @param array<string, mixed>|null $discoveryBudget
     */
    public function setDiscoveryBudget(?array $discoveryBudget): void
    {
        $this->discoveryBudget = $discoveryBudget;
    }

    public function getWebhookUrlOverride(): ?string
    {
        return $this->webhookUrlOverride;
    }

    public function setWebhookUrlOverride(?string $webhookUrlOverride): void
    {
        $this->webhookUrlOverride = $webhookUrlOverride;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    /**
     * @return UcpSigningKeyEntity[]|null
     */
    public function getSigningKeys(): ?array
    {
        return $this->signingKeys;
    }

    /**
     * @param UcpSigningKeyEntity[]|null $signingKeys
     */
    public function setSigningKeys(?array $signingKeys): void
    {
        $this->signingKeys = $signingKeys;
    }

    public function isTransportEnabled(string $transport): bool
    {
        return \in_array($transport, $this->enabledTransports, true);
    }

    public function isCapabilityEnabled(string $capability): bool
    {
        return \in_array($capability, $this->enabledCapabilities, true);
    }

    public function getSignaturePolicy(): string
    {
        return $this->signaturePolicy;
    }

    public function setSignaturePolicy(string $signaturePolicy): void
    {
        $this->signaturePolicy = $signaturePolicy;
    }

    public function isIdempotencyRequired(): bool
    {
        return $this->idempotencyRequired;
    }

    public function setIdempotencyRequired(bool $idempotencyRequired): void
    {
        $this->idempotencyRequired = $idempotencyRequired;
    }
}
