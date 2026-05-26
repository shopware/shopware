<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\AgenticDiscovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifest;
use Shopware\Storefront\Page\AgenticDiscovery\AgenticDiscoveryPage;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryPage::class)]
class AgenticDiscoveryPageTest extends TestCase
{
    public function testExposesTypeAndManifest(): void
    {
        $manifest = self::stubManifest();

        $page = new AgenticDiscoveryPage(AgenticDiscoveryDocumentType::AGENTS_MD, $manifest);

        static::assertSame(AgenticDiscoveryDocumentType::AGENTS_MD, $page->getType());
        static::assertSame($manifest, $page->getManifest());
    }

    public function testHasStableApiAlias(): void
    {
        $page = new AgenticDiscoveryPage(
            AgenticDiscoveryDocumentType::LLMS_TXT,
            self::stubManifest(),
        );

        static::assertSame('agentic_discovery_page', $page->getApiAlias());
    }

    private static function stubManifest(): AgenticManifest
    {
        return new AgenticManifest(
            salesChannelId: 'sc-1',
            storeName: 'Acme',
            storeDescription: '',
            storeUrl: 'https://shop.acme.test',
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
    }
}
