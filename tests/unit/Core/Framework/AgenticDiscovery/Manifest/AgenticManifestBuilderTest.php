<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryConfigProvider;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryDomainResolver;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticDiscoveryContext;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifestBuilder;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\DiscoverySection;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\DiscoverySectionProvider;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AgenticManifestBuilder::class)]
class AgenticManifestBuilderTest extends TestCase
{
    public function testReturnsNullWhenDomainCannotBeResolved(): void
    {
        $builder = $this->createBuilder(
            domain: null,
            config: null,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNull($manifest);
    }

    public function testReturnsNullWhenConfigIsMissing(): void
    {
        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: null,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNull($manifest);
    }

    public function testReturnsNullWhenConfigInactive(): void
    {
        $config = $this->makeConfig(active: false);

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNull($manifest);
    }

    public function testReturnsNullWhenSpecificDocumentDisabled(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setExposeAgentsMd(false);

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNull($manifest);
    }

    public function testBuildsManifestWithStoreIdentity(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomIntro('We focus on quiet luxury.');

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
            systemConfig: [
                'core.basicInformation.shopName' => 'Acme Shop',
                'core.basicInformation.email' => 'hello@acme.test',
                'core.basicInformation.metaAuthor' => 'Acme Team',
            ],
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        static::assertSame('Acme Shop', $manifest->getStoreName());
        static::assertSame('Acme Team', $manifest->getStoreDescription());
        static::assertSame('hello@acme.test', $manifest->getContactEmail());
        static::assertSame('https://shop.acme.test', $manifest->getStoreUrl());
        static::assertSame('en-GB', $manifest->getLanguageCode());
        static::assertSame('EUR', $manifest->getCurrencyCode());
        static::assertSame('We focus on quiet luxury.', $manifest->getCustomIntro());
    }

    public function testIncludesCustomAgentRulesAfterDefaultRules(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomAgentRules(['Do not recommend out-of-stock items unless explicitly asked.']);

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        $rules = $manifest->getRules();
        static::assertGreaterThan(1, \count($rules));
        static::assertContains('Do not recommend out-of-stock items unless explicitly asked.', $rules);
    }

    public function testIncludesSectionsFromProvidersOrderedByPriority(): void
    {
        $config = $this->makeConfig(active: true);

        $low = new class implements DiscoverySectionProvider {
            public function supports(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): bool
            {
                return $type === AgenticDiscoveryDocumentType::AGENTS_MD;
            }

            public function getSection(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): ?DiscoverySection
            {
                return new DiscoverySection('Returns policy', 'We accept returns within 30 days.', 1);
            }
        };

        $high = new class implements DiscoverySectionProvider {
            public function supports(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): bool
            {
                return $type === AgenticDiscoveryDocumentType::AGENTS_MD;
            }

            public function getSection(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): ?DiscoverySection
            {
                return new DiscoverySection('Subscriptions', 'Subscriptions auto-renew.', 100);
            }
        };

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
            sectionProviders: [$low, $high],
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        $sections = $manifest->getCustomSections();
        static::assertCount(2, $sections);
        static::assertSame('Subscriptions', $sections[0]->getTitle());
        static::assertSame('Returns policy', $sections[1]->getTitle());
    }

    public function testSkipsSectionProvidersThatDoNotSupportTheDocument(): void
    {
        $config = $this->makeConfig(active: true);

        $unsupported = new class implements DiscoverySectionProvider {
            public function supports(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): bool
            {
                return false;
            }

            public function getSection(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): ?DiscoverySection
            {
                return new DiscoverySection('Should not appear', '', 0);
            }
        };

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
            sectionProviders: [$unsupported],
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        static::assertSame([], $manifest->getCustomSections());
    }

    public function testIncludesMerchantSectionsFromConfig(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomSections([
            ['title' => 'Brand voice', 'body' => 'Calm, precise, helpful.'],
            ['title' => 'Invalid entry'],
        ]);

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        $sections = $manifest->getCustomSections();
        static::assertCount(1, $sections);
        static::assertSame('Brand voice', $sections[0]->getTitle());
    }

    public function testUcpReferenceFollowsFeatureFlag(): void
    {
        Feature::skipTestIfActive('UCP_SERVER', $this);

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $this->makeConfig(active: true),
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        static::assertFalse($manifest->isUcpAvailable());
        static::assertNull($manifest->getUcpProfileUrl());
    }

    public function testSitemapEntriesReflectExposedDocuments(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setExposeLlmsTxt(false);

        $builder = $this->createBuilder(
            domain: $this->makeDomain(),
            config: $config,
        );

        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTIC_SITEMAP, new Request());

        static::assertNotNull($manifest);
        $urls = array_map(static fn (array $entry): string => $entry['url'], $manifest->getSitemapEntries());
        static::assertContains('https://shop.acme.test/agents.md', $urls);
        static::assertContains('https://shop.acme.test/llms-full.txt', $urls);
        static::assertNotContains('https://shop.acme.test/llms.txt', $urls);
    }

    public function testCustomIntroStripsScriptInjection(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomIntro('Welcome <script>alert(1)</script>shoppers');

        $builder = $this->createBuilder(domain: $this->makeDomain(), config: $config);
        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        static::assertSame('Welcome shoppers', $manifest->getCustomIntro());
    }

    public function testCustomRulesStripIframeAndEventHandlers(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomAgentRules([
            'Avoid <iframe src="//evil"></iframe>autoplaying media.',
            'Note <span onclick="alert(1)">click here</span> for offers.',
        ]);

        $builder = $this->createBuilder(domain: $this->makeDomain(), config: $config);
        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        $rules = $manifest->getRules();
        static::assertContains('Avoid autoplaying media.', $rules);
        // Event-handler attribute is stripped but the surrounding span tag itself stays (safe markdown-passthrough).
        static::assertContains('Note <span>click here</span> for offers.', $rules);
    }

    public function testCustomSectionsRejectJavascriptScheme(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomSections([
            ['title' => 'Returns', 'body' => 'See [policy](javascript:alert(1)) for details.'],
        ]);

        $builder = $this->createBuilder(domain: $this->makeDomain(), config: $config);
        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        $sections = $manifest->getCustomSections();
        static::assertCount(1, $sections);
        static::assertStringNotContainsString('javascript:', $sections[0]->getBody());
        static::assertStringContainsString('about:', $sections[0]->getBody());
    }

    public function testCustomIntroIsTruncatedToMaxLength(): void
    {
        $config = $this->makeConfig(active: true);
        $config->setCustomIntro(str_repeat('A', 5000));

        $builder = $this->createBuilder(domain: $this->makeDomain(), config: $config);
        $manifest = $builder->buildForRequest(AgenticDiscoveryDocumentType::AGENTS_MD, new Request());

        static::assertNotNull($manifest);
        static::assertSame(4000, \strlen((string) $manifest->getCustomIntro()));
    }

    /**
     * @param array<string, mixed> $systemConfig
     * @param iterable<DiscoverySectionProvider> $sectionProviders
     */
    private function createBuilder(
        ?SalesChannelDomainEntity $domain,
        ?AgenticDiscoverySalesChannelConfigEntity $config,
        array $systemConfig = [],
        iterable $sectionProviders = [],
    ): AgenticManifestBuilder {
        $domainResolver = $this->createMock(AgenticDiscoveryDomainResolver::class);
        $domainResolver->method('resolve')->willReturn($domain);

        $configProvider = $this->createMock(AgenticDiscoveryConfigProvider::class);
        $configProvider->method('forSalesChannel')->willReturn($config);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturnCallback(
            static fn (string $key): string => (string) ($systemConfig[$key] ?? '')
        );

        return new AgenticManifestBuilder(
            $domainResolver,
            $configProvider,
            $systemConfigService,
            $sectionProviders,
        );
    }

    private function makeDomain(): SalesChannelDomainEntity
    {
        $locale = new LocaleEntity();
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $language->setLocale($locale);

        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setUrl('https://shop.acme.test');
        $domain->setSalesChannelId(Uuid::randomHex());
        $domain->setLanguage($language);
        $domain->setCurrency($currency);

        return $domain;
    }

    private function makeConfig(bool $active): AgenticDiscoverySalesChannelConfigEntity
    {
        $config = new AgenticDiscoverySalesChannelConfigEntity();
        $config->setUniqueIdentifier(Uuid::randomHex());
        $config->setSalesChannelId(Uuid::randomHex());
        $config->setActive($active);
        $config->setExposeAgentsMd(true);
        $config->setExposeLlmsTxt(true);
        $config->setExposeLlmsFullTxt(true);
        $config->setExposeAgenticSitemap(true);

        return $config;
    }
}
