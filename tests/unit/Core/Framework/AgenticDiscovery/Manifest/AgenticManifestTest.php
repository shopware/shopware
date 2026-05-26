<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifest;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\DiscoverySection;

/**
 * @internal
 */
#[CoversClass(AgenticManifest::class)]
class AgenticManifestTest extends TestCase
{
    public function testExposesAllConstructorFields(): void
    {
        $section = new DiscoverySection('Brand', 'Quiet luxury.', 10);

        $manifest = new AgenticManifest(
            salesChannelId: 'sc-1',
            storeName: 'Acme',
            storeDescription: 'Premium retailer',
            storeUrl: 'https://shop.acme.test',
            languageCode: 'en-GB',
            currencyCode: 'EUR',
            contactEmail: 'hello@acme.test',
            contactPhone: null,
            agentFlow: [['title' => 'Discover', 'description' => 'GET …']],
            endpoints: [['label' => 'Agents manual', 'path' => '/agents.md']],
            catalogEndpoints: [['label' => 'Product', 'path' => '/store-api/product/{id}']],
            rules: ['Checkout MUST require human approval.'],
            browseLinks: [['label' => 'All products', 'url' => 'https://shop.acme.test/']],
            sitemapEntries: [['label' => 'agents.md', 'url' => 'https://shop.acme.test/agents.md', 'changefreq' => 'weekly']],
            customIntro: 'Welcome.',
            customSections: [$section],
            ucpAvailable: true,
            ucpProfileUrl: 'https://shop.acme.test/.well-known/ucp',
        );

        static::assertSame('sc-1', $manifest->getSalesChannelId());
        static::assertSame('Acme', $manifest->getStoreName());
        static::assertSame('Premium retailer', $manifest->getStoreDescription());
        static::assertSame('https://shop.acme.test', $manifest->getStoreUrl());
        static::assertSame('en-GB', $manifest->getLanguageCode());
        static::assertSame('EUR', $manifest->getCurrencyCode());
        static::assertSame('hello@acme.test', $manifest->getContactEmail());
        static::assertNull($manifest->getContactPhone());
        static::assertCount(1, $manifest->getAgentFlow());
        static::assertCount(1, $manifest->getEndpoints());
        static::assertCount(1, $manifest->getCatalogEndpoints());
        static::assertSame(['Checkout MUST require human approval.'], $manifest->getRules());
        static::assertCount(1, $manifest->getBrowseLinks());
        static::assertCount(1, $manifest->getSitemapEntries());
        static::assertSame('Welcome.', $manifest->getCustomIntro());
        static::assertSame([$section], $manifest->getCustomSections());
        static::assertTrue($manifest->isUcpAvailable());
        static::assertSame('https://shop.acme.test/.well-known/ucp', $manifest->getUcpProfileUrl());
    }

    public function testHasStableApiAlias(): void
    {
        $manifest = new AgenticManifest(
            salesChannelId: 'sc-1',
            storeName: '',
            storeDescription: '',
            storeUrl: '',
            languageCode: 'en-GB',
            currencyCode: 'EUR',
            contactEmail: null,
            contactPhone: null,
            agentFlow: [],
            endpoints: [],
            catalogEndpoints: [],
            rules: [],
            browseLinks: [],
            sitemapEntries: [],
            customIntro: null,
            customSections: [],
            ucpAvailable: false,
            ucpProfileUrl: null,
        );

        static::assertSame('agentic_discovery_manifest', $manifest->getApiAlias());
    }
}
