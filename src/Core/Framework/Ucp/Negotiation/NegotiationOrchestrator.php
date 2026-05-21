<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Negotiation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\Capability\CapabilityNegotiator;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Profile\PlatformProfileFetcher;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * The single orchestration entry point that, given an incoming UCP request:
 *
 *   1. Fetches and validates the platform profile (via cache)
 *   2. Checks protocol version compatibility
 *   3. Computes the capability intersection
 *   4. Returns the negotiated set
 *
 * Throws UcpException with the right code so the exception listener can emit
 * the spec-correct status code.
 *
 * @internal
 */
#[Package('framework')]
class NegotiationOrchestrator
{
    public function __construct(
        private readonly PlatformProfileFetcher $platformProfileFetcher,
        private readonly CapabilityNegotiator $capabilityNegotiator,
        private readonly CapabilityRegistry $capabilityRegistry,
    ) {
    }

    public function negotiate(
        UcpSalesChannelConfigEntity $config,
        string $platformProfileUri,
        Context $context
    ): CapabilityIntersection {
        $platformProfile = $this->platformProfileFetcher->fetch($platformProfileUri, $context, $config->getPlatformAllowlist());
        $platformUcp = $platformProfile['ucp'] ?? [];
        if (!\is_array($platformUcp)) {
            throw UcpException::profileMalformed($platformProfileUri, 'Missing ucp object');
        }

        $platformVersion = $platformUcp['version'] ?? null;
        if (!\is_string($platformVersion) || !UcpVersion::isValidFormat($platformVersion)) {
            throw UcpException::profileMalformed($platformProfileUri, 'Invalid ucp.version');
        }

        if (!$this->isVersionAccepted($config, $platformVersion)) {
            throw UcpException::versionUnsupported($platformVersion, $config->getUcpVersion());
        }

        $businessCapabilities = $this->buildBusinessCapabilities($config);
        $platformCapabilities = $platformUcp['capabilities'] ?? [];
        if (!\is_array($platformCapabilities)) {
            $platformCapabilities = [];
        }

        return $this->capabilityNegotiator->negotiate(
            $businessCapabilities,
            $platformCapabilities,
            $platformVersion
        );
    }

    public function negotiateConformancePlaceholder(UcpSalesChannelConfigEntity $config, string $platformVersion): CapabilityIntersection
    {
        if (!$this->isVersionAccepted($config, $platformVersion)) {
            throw UcpException::versionInvalid($platformVersion);
        }

        $capabilities = $this->buildBusinessCapabilities($config);
        unset($capabilities['dev.ucp.shopping.ap2_mandate']);

        return new CapabilityIntersection($capabilities, $platformVersion);
    }

    private function isVersionAccepted(UcpSalesChannelConfigEntity $config, string $platformVersion): bool
    {
        if ($platformVersion === $config->getUcpVersion()) {
            return true;
        }

        return \in_array($platformVersion, UcpVersion::HISTORICAL, true);
    }

    /**
     * Build the business capabilities map in the same shape the negotiator expects:
     *
     *   [name => [ ["version" => "...", "extends" => "...", "spec" => "..."], ... ]]
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildBusinessCapabilities(UcpSalesChannelConfigEntity $config): array
    {
        $out = [];
        foreach ($config->getEnabledCapabilities() as $name) {
            $capability = $this->capabilityRegistry->get($name);
            if ($capability === null) {
                continue;
            }
            $entry = ['version' => $capability->getVersion()];
            $extends = $capability->getExtends();
            if ($extends !== null) {
                $entry['extends'] = $extends;
            }
            $out[$name] = [$entry];
        }

        return $out;
    }
}
