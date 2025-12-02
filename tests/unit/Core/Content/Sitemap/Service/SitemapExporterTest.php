<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Shopware\Core\Content\Sitemap\Service\SitemapExporter;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Shopware\Core\Content\Sitemap\SitemapException;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
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
        $customerUrlProvider->expects($this->once())->method('getUrls')->willReturn($urlResult);

        $sitemapHandler1 = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandler2 = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandlerFactory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandlerFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $sitemapHandler1,
                $sitemapHandler2
            );

        $cacheItemPoolInterface = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemPoolInterface->method('getItem')->willReturn(new CacheItem());

        $exporter = $this->createSitemapExporter($cacheItemPoolInterface, [$customerUrlProvider], $sitemapHandlerFactory);

        $languageId = Uuid::randomHex();
        $salesChannel = $this->createSalesChannel('testSalesChannel', $languageId);

        $domainA = $this->createSalesChannelDomain('testDomainA', 'https://test.com/', $languageId);
        $domainB = $this->createSalesChannelDomain('testDomainB', 'https://test.com', $languageId);

        $salesChannel->setDomains(new SalesChannelDomainCollection([$domainA, $domainB]));

        $salesChannelContext = $this->createSalesChannelContext($salesChannel, []);

        $expectedUrls = [];
        foreach ($urls as $url) {
            $expectedUrl = clone $url;
            $expectedUrl->setLoc('https://test.com/' . $url->getLoc());
            $expectedUrls[] = $expectedUrl;
        }

        $sitemapHandler1->expects($this->once())->method('write')->with($expectedUrls);
        $sitemapHandler2->expects($this->once())->method('write')->with($expectedUrls);
        $exporter->generate($salesChannelContext);
    }

    public function testDoesNotRefreshSalesChannelWithRules(): void
    {
        $salesChannel = $this->createSalesChannel('salesChannelWithRules');
        $rules = array_map(fn () => Uuid::randomHex(), range(0, 2));

        $domain = $this->createSalesChannelDomain('testDomain', 'https://test.com', $salesChannel->getLanguageId());
        $salesChannel->setDomains(new SalesChannelDomainCollection([$domain]));

        $salesChannelContext = $this->createSalesChannelContext($salesChannel, $rules);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $exporter = $this->createSitemapExporter(cache: $cache, cartRuleLoader: $cartRuleLoader);
        $exporter->generate($salesChannelContext);

        $cartRuleLoader->expects($this->never())->method('loadByToken');
    }

    public function testGenerateThrowsExceptionINoSitemapHandlesCreated(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItemMock());

        $exporter = $this->createSitemapExporter($cache);

        $salesChannel = $this->createSalesChannel('testSalesChannel');
        $salesChannelContext = $this->createSalesChannelContext($salesChannel, []);

        $this->expectException(SitemapException::class);
        $this->expectExceptionMessage('Invalid domain');
        $exporter->generate($salesChannelContext, true);
    }

    public function testGenerateThrowsExceptionIfSitemapIsAlreadyLocked(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItemMock());

        $exporter = $this->createSitemapExporter($cache);

        $salesChannel = $this->createSalesChannel('testSalesChannel');
        $salesChannelContext = $this->createSalesChannelContext($salesChannel, []);

        $this->expectException(SitemapException::class);
        $this->expectExceptionMessage('Cannot acquire lock for sales channel testSalesChannel and language ' . $salesChannel->getLanguageId());
        $exporter->generate($salesChannelContext);
    }

    /**
     * @param iterable<AbstractUrlProvider>|null $urlProvider
     */
    private function createSitemapExporter(
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

    private function createSalesChannel(
        string $salesChannelId,
        ?string $languageId = null
    ): SalesChannelEntity {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguageId($languageId ?? Uuid::randomHex());

        return $salesChannel;
    }

    private function createSalesChannelDomain(
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

    /**
     * @param array<string> $ruleIds
     */
    private function createSalesChannelContext(SalesChannelEntity $salesChannel, array $ruleIds): SalesChannelContext
    {
        $context = new Context(
            source: new SystemSource(),
            ruleIds: $ruleIds,
            languageIdChain: [$salesChannel->getLanguageId()],
        );

        return Generator::generateSalesChannelContext(
            baseContext: $context,
            salesChannel: $salesChannel,
        );
    }
}

/**
 * @internal
 */
class CacheItemMock implements CacheItemInterface
{
    public function getKey(): string
    {
        return Uuid::randomHex();
    }

    public function get(): mixed
    {
        return null;
    }

    public function isHit(): bool
    {
        return true;
    }

    public function set(mixed $value): static
    {
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
