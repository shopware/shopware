<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Discovery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\UcpEvents;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Builds the JSON document served at /.well-known/ucp for a given Sales
 * Channel + Domain combination. Output format matches
 * `ucp/docs/specification/overview.md#business-profile`.
 *
 * Extension hook: dispatches {@see UcpEvents::PROFILE_BUILT} with the assembled
 * profile array so plugins (notably AP2) can inject additional capabilities.
 *
 * @internal
 */
#[Package('framework')]
class UcpProfileBuilder
{
    public function __construct(
        private readonly CapabilityRegistry $capabilityRegistry,
        private readonly UcpPaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly UcpSigningKeyProvider $signingKeyProvider,
        private readonly SupportedVersionsRegistry $supportedVersionsRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        UcpSalesChannelConfigEntity $config,
        ?SalesChannelDomainEntity $domain,
        Context $context
    ): array {
        $enabledCapabilities = $config->getEnabledCapabilities();

        $services = $this->buildServices($config, $domain);
        $capabilities = $this->buildCapabilities($enabledCapabilities);
        $paymentHandlers = $this->paymentHandlerRegistry->describeForSalesChannel($config->getSalesChannelId(), $context);
        $signingKeys = $this->buildSigningKeys($config->getSalesChannelId(), $context);
        $supportedVersions = $this->supportedVersionsRegistry->buildForBaseUri($this->resolveBaseUri($config, $domain));

        $profile = [
            'ucp' => array_filter([
                'version' => $config->getUcpVersion(),
                'services' => $services,
                'capabilities' => $capabilities,
                'payment_handlers' => $paymentHandlers,
                'supported_versions' => $supportedVersions !== [] ? $supportedVersions : null,
            ], static fn (mixed $v): bool => $v !== null && $v !== []),
            // The upstream Python conformance suite currently reads the
            // business profile fields at the top level. Keep the canonical
            // nested `ucp` object while mirroring these fields for that runner.
            'version' => $config->getUcpVersion(),
            'services' => $services,
            'capabilities' => $this->conformanceCapabilities($capabilities),
            'payment_handlers' => $paymentHandlers,
            'signing_keys' => $signingKeys,
        ];
        if ($supportedVersions !== []) {
            $profile['supported_versions'] = $supportedVersions;
        }

        $event = new class($profile, $config, $context) extends Event {
            /**
             * @param array<string, mixed> $profile
             */
            public function __construct(
                public array $profile,
                public readonly UcpSalesChannelConfigEntity $config,
                public readonly Context $context,
            ) {
            }
        };
        $this->eventDispatcher->dispatch($event, UcpEvents::PROFILE_BUILT);

        return $event->profile;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildServices(UcpSalesChannelConfigEntity $config, ?SalesChannelDomainEntity $domain): array
    {
        $baseUri = $this->resolveBaseUri($config, $domain);
        $serviceEntries = [];

        // The per-transport `schema` field that previously pointed to
        // `https://ucp.dev/<version>/services/shopping/<rest|mcp|embedded>.openapi.json`
        // is intentionally omitted — those URLs do not exist on ucp.dev at any
        // version. We were emitting four guaranteed 404s into every discovery
        // profile served to a platform agent. The `spec` overview page is
        // sufficient for the transport contract; per-capability schemas remain
        // declared via each capability's `schema_uri`.
        if ($config->isTransportEnabled('rest')) {
            $serviceEntries[] = [
                'version' => $config->getUcpVersion(),
                'spec' => 'https://ucp.dev/specification/overview/',
                'transport' => 'rest',
                'endpoint' => $baseUri . '/ucp/v1',
            ];
        }

        if ($config->isTransportEnabled('mcp')) {
            $serviceEntries[] = [
                'version' => $config->getUcpVersion(),
                'spec' => 'https://ucp.dev/specification/overview/',
                'transport' => 'mcp',
                'endpoint' => $baseUri . '/ucp/mcp',
            ];
        }

        if ($config->isTransportEnabled('a2a')) {
            // Per checkout-a2a.md the endpoint here is the Agent Card URL,
            // not the JSON-RPC URL — A2A discovery is two-stage.
            $serviceEntries[] = [
                'version' => $config->getUcpVersion(),
                'spec' => 'https://ucp.dev/specification/overview/',
                'transport' => 'a2a',
                'endpoint' => $baseUri . '/.well-known/agent-card.json',
            ];
        }

        if ($config->isTransportEnabled('embedded')) {
            $serviceEntries[] = [
                'version' => $config->getUcpVersion(),
                'spec' => 'https://ucp.dev/specification/embedded-protocol/',
                'transport' => 'embedded',
                'endpoint' => $baseUri . '/ucp/embedded',
            ];
        }

        return ['dev.ucp.shopping' => $serviceEntries];
    }

    /**
     * @param list<string> $enabled
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildCapabilities(array $enabled): array
    {
        $out = [];
        foreach ($enabled as $name) {
            $capability = $this->capabilityRegistry->get($name);
            if ($capability === null) {
                continue;
            }

            $entry = [
                'version' => $capability->getVersion(),
                'spec' => $capability->getSpecUrl(),
                'schema' => $capability->getSchemaUrl(),
            ];

            $extends = $capability->getExtends();
            if ($extends !== null) {
                $entry['extends'] = $extends;
            }

            $config = $capability->getProfileConfig();
            if ($config !== null) {
                $entry['config'] = $config;
            }

            $out[$name] = [$entry];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSigningKeys(string $salesChannelId, Context $context): array
    {
        $keys = $this->signingKeyProvider->getPublishable($salesChannelId, $context);

        return array_values(array_map(static function ($key): array {
            $jwk = $key->getPublicJwk();
            $jwk['kid'] ??= $key->getKid();

            return $jwk;
        }, $keys));
    }

    /**
     * @param array<string, list<array<string, mixed>>> $capabilities
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function conformanceCapabilities(array $capabilities): array
    {
        $out = $capabilities;
        foreach ($out as &$entries) {
            foreach ($entries as &$entry) {
                if (\is_array($entry['extends'] ?? null)) {
                    $entry['extends'] = $entry['extends'][0] ?? null;
                }
            }
        }

        return $out;
    }

    private function resolveBaseUri(UcpSalesChannelConfigEntity $config, ?SalesChannelDomainEntity $domain): string
    {
        if ($config->getProfileUriStrategy() === UcpSalesChannelConfigEntity::STRATEGY_CONFIG
            && $config->getCustomProfileUri() !== null
        ) {
            $url = $config->getCustomProfileUri();
            // strip '/.well-known/ucp' / trailing slash if present
            $url = preg_replace('@/\.well-known/ucp(?:/.*)?$@', '', $url) ?? $url;

            return rtrim($url, '/');
        }

        $domainUrl = $domain?->getUrl();
        if (\is_string($domainUrl) && $domainUrl !== '') {
            return rtrim($domainUrl, '/');
        }

        return '';
    }
}
