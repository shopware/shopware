<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Manifest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Pre-rendered view-model handed to the discovery Twig templates. Carries
 * everything the templates need to render `/agents.md`, `/llms.txt`,
 * `/llms-full.txt` and `/sitemap_agentic_discovery.xml` without touching
 * services from the template layer.
 *
 * Templates use `manifest.*` accessors only; future fields can be added
 * here without breaking existing themes that override blocks.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 */
#[Package('framework')]
class AgenticManifest extends Struct
{
    /**
     * @param list<array{title: string, description: string}> $agentFlow
     * @param list<array{label: string, path: string}> $endpoints
     * @param list<array{label: string, path: string}> $catalogEndpoints
     * @param list<string> $rules
     * @param list<array{label: string, url: string}> $browseLinks
     * @param list<array{label: string, url: string, changefreq?: string}> $sitemapEntries
     * @param list<DiscoverySection> $customSections
     */
    public function __construct(
        private readonly string $salesChannelId,
        private readonly string $storeName,
        private readonly string $storeDescription,
        private readonly string $storeUrl,
        private readonly string $languageCode,
        private readonly string $currencyCode,
        private readonly ?string $contactEmail,
        private readonly ?string $contactPhone,
        private readonly array $agentFlow,
        private readonly array $endpoints,
        private readonly array $catalogEndpoints,
        private readonly array $rules,
        private readonly array $browseLinks,
        private readonly array $sitemapEntries,
        private readonly ?string $customIntro,
        private readonly array $customSections,
        private readonly bool $ucpAvailable,
        private readonly ?string $ucpProfileUrl,
    ) {
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getStoreName(): string
    {
        return $this->storeName;
    }

    public function getStoreDescription(): string
    {
        return $this->storeDescription;
    }

    public function getStoreUrl(): string
    {
        return $this->storeUrl;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    /**
     * @return list<array{title: string, description: string}>
     */
    public function getAgentFlow(): array
    {
        return $this->agentFlow;
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    public function getCatalogEndpoints(): array
    {
        return $this->catalogEndpoints;
    }

    /**
     * @return list<string>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function getBrowseLinks(): array
    {
        return $this->browseLinks;
    }

    /**
     * @return list<array{label: string, url: string, changefreq?: string}>
     */
    public function getSitemapEntries(): array
    {
        return $this->sitemapEntries;
    }

    public function getCustomIntro(): ?string
    {
        return $this->customIntro;
    }

    /**
     * @return list<DiscoverySection>
     */
    public function getCustomSections(): array
    {
        return $this->customSections;
    }

    public function isUcpAvailable(): bool
    {
        return $this->ucpAvailable;
    }

    public function getUcpProfileUrl(): ?string
    {
        return $this->ucpProfileUrl;
    }

    public function getApiAlias(): string
    {
        return 'agentic_discovery_manifest';
    }
}
