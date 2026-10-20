<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Sitemap\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class SitemapRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testEmpty(): void
    {
        $this->browser->request('POST', '/store-api/sitemap');

        static::assertNotFalse($this->browser->getResponse()->getContent());

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertCount(0, $response);
    }

    public function testSitemapListsEntries(): void
    {
        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create('', $this->ids->get('sales-channel'));

        $fs = static::getContainer()->get('shopware.filesystem.sitemap');
        $fs->write('sitemap/salesChannel-' . $context->getSalesChannelId() . '-' . $context->getLanguageId() . '/test.xml', 'some content');

        $this->browser->request('POST', '/store-api/sitemap');

        static::assertNotFalse($this->browser->getResponse()->getContent());

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response);
        static::assertSame('sitemap', $response[0]['apiAlias']);
        static::assertArrayHasKey('filename', $response[0]);
        static::assertArrayHasKey('created', $response[0]);
        static::assertNotEmpty($response[0]['filename']);
        static::assertNotEmpty($response[0]['created']);
    }

    public function testLiveStrategyHeadlessWithoutExternalStorefrontDomainReturnsEmptyList(): void
    {
        $browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('headless-sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://default.headless.test',
            ]],
        ]);

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.sitemap.sitemapRefreshStrategy', SitemapExporterInterface::STRATEGY_LIVE);

        $browser->request('POST', '/store-api/sitemap');

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        // without an external storefront domain the generation is skipped silently instead of failing
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertCount(0, $response);
    }

    public function testLiveStrategyHeadlessWithExternalStorefrontDomainGeneratesSitemap(): void
    {
        $browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('headless-sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://frontend.test',
                'isExternalStorefront' => true,
            ]],
        ]);

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.sitemap.sitemapRefreshStrategy', SitemapExporterInterface::STRATEGY_LIVE);

        $browser->request('POST', '/store-api/sitemap');

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertNotEmpty($response);

        foreach ($response as $sitemap) {
            // the external storefront does not serve the files, so the listing must not point at it
            static::assertStringNotContainsString('frontend.test', $sitemap['filename']);
        }
    }
}
