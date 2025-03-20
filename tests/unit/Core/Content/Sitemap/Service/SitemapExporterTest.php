<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Shopware\Core\Content\Sitemap\Service\SitemapExporter;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(SitemapExporter::class)]
class SitemapExporterTest extends TestCase
{
    public function testGenerate(): void
    {
        $urlItems = [
            [
                'url' => '',
            ],
            [
                'url' => 'test/',
            ],
            [
                'url' => 'test',
            ],
        ];

        $urls = [];
        foreach ($urlItems as $item) {
            $url = new Url();
            $url->setLoc($item['url']);

            $urls[] = $url;
        }

        $urlResult = new UrlResult($urls, null);

        $customerUrlProvider = $this->createMock(CustomUrlProvider::class);
        $customerUrlProvider->expects(static::once())->method('getUrls')->willReturn($urlResult);

        $sitemapHandler1 = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandler2 = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandlerFactory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandlerFactory->expects(static::exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $sitemapHandler1,
                $sitemapHandler2
            );

        $cacheItemPoolInterface = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemPoolInterface->method('getItem')->willReturn(new CacheItem());

        $exporter = $this->sitemapExporter($cacheItemPoolInterface, [$customerUrlProvider], $sitemapHandlerFactory);

        $languageId = Uuid::randomHex();
        $salesChannel = $this->salesChannel('testSalesChannel', $languageId);

        $domainA = $this->salesChannelDomain('testDomainA', 'https://test.com/', $languageId);
        $domainB = $this->salesChannelDomain('testDomainB', 'https://test.com', $languageId);

        $salesChannel->setDomains(new SalesChannelDomainCollection([$domainA, $domainB]));

        $salesChannelContext = $this->mockSalesChannelContext($salesChannel);

        $expectedUrls = [];
        foreach ($urls as $url) {
            $expectedUrl = clone $url;
            $expectedUrl->setLoc('https://test.com/' . $url->getLoc());
            $expectedUrls[] = $expectedUrl;
        }

        $sitemapHandler1->expects(static::once())->method('write')->with($expectedUrls);
        $sitemapHandler2->expects(static::once())->method('write')->with($expectedUrls);
        $exporter->generate($salesChannelContext);
    }

    public function testDoesNotRefreshSalesChannelWithRules(): void
    {
        $salesChannel = $this->salesChannel('salesChannelWithRules');
        $salesChannelRules = array_map(fn () => Uuid::randomHex(), range(0, 2));

        $domain = $this->salesChannelDomain('testDomain', 'https://test.com', $salesChannel->getLanguageId());
        $salesChannel->setDomains(new SalesChannelDomainCollection([$domain]));

        $salesChannelContext = $this->mockSalesChannelContext($salesChannel);
        $salesChannelContext->expects(static::once())->method('getRuleIds')->willReturn($salesChannelRules);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $exporter = $this->sitemapExporter($cache, cartRuleLoader: $cartRuleLoader);
        $exporter->generate($salesChannelContext);

        $salesChannelContext->expects(static::never())->method('getToken');
        $cartRuleLoader->expects(static::never())->method('loadByToken');
    }

    /**
     * @param iterable<AbstractUrlProvider>|null $urlProvider
     */
    private function sitemapExporter(
        CacheItemPoolInterface&MockObject $cache,
        ?iterable $urlProvider = null,
        (SitemapHandleFactoryInterface&MockObject)|null $sitemapHandleFactory = null,
        ?CartRuleLoader $cartRuleLoader = null
    ): SitemapExporter {
        return new SitemapExporter(
            $urlProvider ?? [],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $sitemapHandleFactory ?? $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class),
            $cartRuleLoader ?? $this->createMock(CartRuleLoader::class)
        );
    }

    private function salesChannel(
        string $salesChannelId,
        ?string $languageId = null
    ): SalesChannelEntity {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguageId($languageId ?? Uuid::randomHex());

        return $salesChannel;
    }

    private function salesChannelDomain(
        string $domainId,
        string $domainUrl,
        ?string $languageId = null
    ): SalesChannelDomainEntity {
        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setId($domainId);
        $salesChannelDomain->setUrl($domainUrl);
        $salesChannelDomain->setLanguageId($languageId ?? Uuid::randomHex());

        return $salesChannelDomain;
    }

    private function mockSalesChannelContext(
        SalesChannelEntity $salesChannel,
    ): SalesChannelContext&MockObject {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getLanguageId')->willReturn($salesChannel->getLanguageId());

        return $salesChannelContext;
    }
}
