<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
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

        $exporter = new SitemapExporter(
            [
                $customerUrlProvider,
            ],
            $cacheItemPoolInterface,
            10,
            $this->createMock(FilesystemOperator::class),
            $sitemapHandlerFactory,
            $this->createMock(EventDispatcher::class)
        );

        $languageId = Uuid::randomHex();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('testSalesChannel');
        $salesChannel->setLanguageId($languageId);

        $domainA = new SalesChannelDomainEntity();
        $domainA->setId('testDomainA');
        $domainA->setUrl('https://test.com/');
        $domainA->setLanguageId($languageId);

        $domainB = new SalesChannelDomainEntity();
        $domainB->setId('testDomainB');
        $domainB->setUrl('https://test.com');
        $domainB->setLanguageId($languageId);

        $salesChannel->setDomains(new SalesChannelDomainCollection([$domainA, $domainB]));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getLanguageId')->willReturn($languageId);

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
}
