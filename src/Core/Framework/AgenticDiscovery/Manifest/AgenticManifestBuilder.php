<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Manifest;

use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryConfigProvider;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryDomainResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Aggregates store identity, system config, sales-channel-domain data and
 * contributions from `DiscoverySectionProvider` implementations into a single
 * immutable `AgenticManifest` consumed by the discovery Twig templates.
 *
 * Resolution path:
 *  1. Resolve the storefront SalesChannelDomain from the incoming request.
 *  2. Load the discovery configuration for that sales channel.
 *  3. Bail out (caller serves 404) when discovery is disabled.
 *  4. Compose a manifest from defaults + system config + per-channel custom
 *     values + tagged section providers.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticManifestBuilder
{
    /**
     * Default agent flow steps quoted into /agents.md. Mirrors the six-step
     * Discover → Search → Cart → Checkout → Fulfill → Complete sequence that
     * has emerged as the de-facto convention across agentic commerce
     * platforms, so any UCP-compatible agent reading multiple stores in the
     * same session sees a consistent
     * vocabulary.
     */
    private const DEFAULT_AGENT_FLOW = [
        ['title' => 'Discover', 'description' => 'GET `/.well-known/ucp` to confirm capabilities and supported transports.'],
        ['title' => 'Search', 'description' => 'Use the Store API or UCP catalog search to find products matching the buyer intent.'],
        ['title' => 'Cart', 'description' => 'Add desired items to a cart via the Store API or UCP cart capability.'],
        ['title' => 'Checkout', 'description' => 'Start the purchase flow via the Store API or UCP checkout capability.'],
        ['title' => 'Fulfill', 'description' => 'Provide shipping address and method. Recalculate totals before completion.'],
        ['title' => 'Complete', 'description' => 'Finalize the order — payment MUST require explicit human approval.'],
    ];

    private const DEFAULT_RULES = [
        'Checkout MUST require human approval. Do not auto-confirm payment.',
        'Respect `429 Too Many Requests`. Back off exponentially before retrying.',
        'Pass language and currency context headers so prices and stock reflect the buyer\'s region.',
        'Prices and inventory in any response are authoritative at request time only. Re-check before completing checkout.',
        'Do not claim properties about products that are not present in the structured catalog data.',
    ];

    /**
     * Merchant-provided text fields (`customIntro`, `customAgentRules`,
     * `customSections.body`) are written by privileged admins (ACL
     * `sales_channel.editor`) and rendered verbatim into Markdown documents.
     * Markdown allows embedded HTML, so a downstream renderer that converts
     * the document to interactive HTML could execute attacker-controlled
     * markup. We sanitize at *render* time (not at write time) so existing
     * stored values are also covered, and so that a future relaxation of the
     * policy does not require a data migration.
     */
    private const MERCHANT_TEXT_MAX_LENGTH = 4000;

    /**
     * @param iterable<DiscoverySectionProvider> $sectionProviders
     */
    public function __construct(
        private readonly AgenticDiscoveryDomainResolver $domainResolver,
        private readonly AgenticDiscoveryConfigProvider $configProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly iterable $sectionProviders,
    ) {
    }

    /**
     * Returns the manifest for the given document on the given request,
     * or `null` if discovery is not configured for the resolved domain or
     * the specific document is disabled by the merchant.
     */
    public function buildForRequest(AgenticDiscoveryDocumentType $type, Request $request): ?AgenticManifest
    {
        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            return null;
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive()) {
            return null;
        }

        if (!$this->isDocumentExposed($type, $config)) {
            return null;
        }

        return $this->compose($type, new AgenticDiscoveryContext($domain, $config, $context));
    }

    private function isDocumentExposed(AgenticDiscoveryDocumentType $type, AgenticDiscoverySalesChannelConfigEntity $config): bool
    {
        return match ($type) {
            AgenticDiscoveryDocumentType::AGENTS_MD => $config->isExposeAgentsMd(),
            AgenticDiscoveryDocumentType::LLMS_TXT => $config->isExposeLlmsTxt(),
            AgenticDiscoveryDocumentType::LLMS_FULL_TXT => $config->isExposeLlmsFullTxt(),
            AgenticDiscoveryDocumentType::AGENTIC_SITEMAP => $config->isExposeAgenticSitemap(),
        };
    }

    private function compose(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): AgenticManifest
    {
        $domain = $context->getDomain();
        $config = $context->getConfig();
        $salesChannelId = $domain->getSalesChannelId();

        $domainUrl = $context->getDomainUrl();
        $storeName = $this->resolveStoreName($domain, $salesChannelId);
        $storeDescription = $this->resolveStoreDescription($salesChannelId);

        $contactEmail = $this->systemConfigService->getString('core.basicInformation.email', $salesChannelId) ?: null;

        $languageCode = $this->resolveLanguageCode($domain);
        $currencyCode = $this->resolveCurrencyCode($domain);

        $ucpAvailable = Feature::isActive('UCP_SERVER');
        $ucpProfileUrl = $ucpAvailable ? $domainUrl . '/.well-known/ucp' : null;

        $rules = self::DEFAULT_RULES;
        if ($config->getCustomAgentRules() !== null) {
            $sanitizedRules = array_map(
                fn (string $rule): string => $this->sanitizeMerchantText($rule),
                array_filter($config->getCustomAgentRules(), 'is_string')
            );
            // Drop entries that sanitization fully emptied out.
            $rules = array_values(array_merge($rules, array_filter($sanitizedRules, static fn (string $r): bool => $r !== '')));
        }

        $browseLinks = [
            ['label' => 'All products', 'url' => $domainUrl . '/'],
            ['label' => 'Search', 'url' => $domainUrl . '/search?search={query}'],
            ['label' => 'Sitemap', 'url' => $domainUrl . '/sitemap.xml'],
        ];

        $endpoints = [
            ['label' => 'Agent operating manual', 'path' => '/agents.md'],
            ['label' => 'LLM curator overview', 'path' => '/llms.txt'],
            ['label' => 'LLM extended overview', 'path' => '/llms-full.txt'],
            ['label' => 'Agentic discovery sitemap', 'path' => '/sitemap_agentic_discovery.xml'],
            ['label' => 'Standard sitemap', 'path' => '/sitemap.xml'],
        ];
        if ($ucpAvailable) {
            $endpoints[] = ['label' => 'UCP capability profile', 'path' => '/.well-known/ucp'];
        }

        $catalogEndpoints = [
            ['label' => 'Store API: product detail', 'path' => '/store-api/product/{id}'],
            ['label' => 'Store API: search', 'path' => '/store-api/search'],
            ['label' => 'Store API: category tree', 'path' => '/store-api/category'],
            ['label' => 'Store API: cart', 'path' => '/store-api/checkout/cart'],
        ];

        $sitemapEntries = [];
        if ($config->isExposeLlmsTxt()) {
            $sitemapEntries[] = ['label' => 'llms.txt', 'url' => $domainUrl . '/llms.txt', 'changefreq' => 'weekly'];
        }
        if ($config->isExposeLlmsFullTxt()) {
            $sitemapEntries[] = ['label' => 'llms-full.txt', 'url' => $domainUrl . '/llms-full.txt', 'changefreq' => 'weekly'];
        }
        if ($config->isExposeAgentsMd()) {
            $sitemapEntries[] = ['label' => 'agents.md', 'url' => $domainUrl . '/agents.md', 'changefreq' => 'weekly'];
        }

        $customSections = $this->collectSections($type, $context);
        $customSections = array_merge($customSections, $this->buildMerchantSections($config));

        usort(
            $customSections,
            static fn (DiscoverySection $a, DiscoverySection $b): int => $b->getPriority() <=> $a->getPriority()
        );

        $customIntro = $config->getCustomIntro() !== null
            ? $this->sanitizeMerchantText($config->getCustomIntro())
            : null;

        return new AgenticManifest(
            salesChannelId: $salesChannelId,
            storeName: $storeName,
            storeDescription: $storeDescription,
            storeUrl: $domainUrl,
            languageCode: $languageCode,
            currencyCode: $currencyCode,
            contactEmail: $contactEmail,
            contactPhone: null,
            agentFlow: self::DEFAULT_AGENT_FLOW,
            endpoints: $endpoints,
            catalogEndpoints: $catalogEndpoints,
            rules: $rules,
            browseLinks: $browseLinks,
            sitemapEntries: $sitemapEntries,
            customIntro: $customIntro === '' ? null : $customIntro,
            customSections: $customSections,
            ucpAvailable: $ucpAvailable,
            ucpProfileUrl: $ucpProfileUrl,
        );
    }

    private function resolveStoreName(SalesChannelDomainEntity $domain, string $salesChannelId): string
    {
        $configured = $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
        if ($configured !== '') {
            return $configured;
        }

        $salesChannel = $domain->getSalesChannel();

        return $salesChannel?->getName() ?? '';
    }

    private function resolveStoreDescription(string $salesChannelId): string
    {
        $author = $this->systemConfigService->getString('core.basicInformation.metaAuthor', $salesChannelId);

        return $author !== '' ? $author : '';
    }

    private function resolveLanguageCode(SalesChannelDomainEntity $domain): string
    {
        $language = $domain->getLanguage();
        if ($language === null) {
            return 'en-GB';
        }

        $locale = $language->getLocale();

        return $locale?->getCode() ?? 'en-GB';
    }

    private function resolveCurrencyCode(SalesChannelDomainEntity $domain): string
    {
        $currency = $domain->getCurrency();

        return $currency?->getIsoCode() ?? 'EUR';
    }

    /**
     * @return list<DiscoverySection>
     */
    private function collectSections(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): array
    {
        $sections = [];
        foreach ($this->sectionProviders as $provider) {
            if (!$provider->supports($type, $context)) {
                continue;
            }

            $section = $provider->getSection($type, $context);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @return list<DiscoverySection>
     */
    private function buildMerchantSections(AgenticDiscoverySalesChannelConfigEntity $config): array
    {
        $sections = [];
        foreach ($config->getCustomSections() ?? [] as $section) {
            $title = $section['title'] ?? null;
            $body = $section['body'] ?? null;
            if (!\is_string($title) || $title === '' || !\is_string($body) || $body === '') {
                continue;
            }
            $cleanTitle = $this->sanitizeMerchantText($title);
            $cleanBody = $this->sanitizeMerchantText($body);
            if ($cleanTitle === '' || $cleanBody === '') {
                continue;
            }
            $sections[] = new DiscoverySection($cleanTitle, $cleanBody, -10);
        }

        return $sections;
    }

    /**
     * Removes HTML payloads that could become executable when a downstream
     * agent renders the Markdown document to HTML, plus dangerous URI schemes
     * inside Markdown links. Markdown formatting itself (emphasis, lists,
     * headings, code blocks, links with safe schemes) is intentionally
     * preserved.
     *
     * The input comes from a privileged admin user (ACL
     * `sales_channel.editor`); this routine is defense-in-depth, not the
     * primary access control.
     */
    private function sanitizeMerchantText(string $text): string
    {
        // Strip dangerous HTML elements (and their contents for paired tags).
        $text = (string) preg_replace(
            '/<(script|style|iframe|object|embed|svg|math|noscript)\b[^>]*>.*?<\/\1>/is',
            '',
            $text
        );
        // Strip self-closing or unclosed dangerous tags.
        $text = (string) preg_replace(
            '/<(script|style|iframe|object|embed|svg|math|noscript|link|meta|base|form|input|button)\b[^>]*\/?>/i',
            '',
            $text
        );
        // Strip event-handler attributes anywhere in the remaining text
        // (`onclick=`, `onerror=`, etc.).
        $text = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text);
        // Neutralise dangerous URI schemes inside Markdown link targets and
        // raw HTML href/src attributes.
        $text = (string) preg_replace('/\b(javascript|vbscript|data:text\/html)\s*:/i', 'about:', $text);

        $text = trim($text);

        if (\strlen($text) > self::MERCHANT_TEXT_MAX_LENGTH) {
            $text = substr($text, 0, self::MERCHANT_TEXT_MAX_LENGTH);
        }

        return $text;
    }
}
