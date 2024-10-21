<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Service\SitemapExporter;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('services-settings')]
class SitemapExporterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private SalesChannelContext $context;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'sitemap-exporter-test');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
    }

    public function testNotLocked(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, false));

        $exporter = new SitemapExporter(
            [],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class)
        );

        $result = $exporter->generate($this->context);

        static::assertTrue($result->isFinish());
    }

    public function testExpectAlreadyLockedException(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, true));

        $exporter = new SitemapExporter(
            [],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class)
        );

        $this->expectException(AlreadyLockedException::class);
        $exporter->generate($this->context);
    }

    public function testForce(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, true));

        $exporter = new SitemapExporter(
            [],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class)
        );

        $result = $exporter->generate($this->context, true);

        static::assertTrue($result->isFinish());
    }

    public function testLocksAndUnlocks(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = null;
        $cache->method('getItem')->willReturnCallback(function (string $k) use (&$cacheItem) {
            if ($cacheItem === null) {
                $cacheItem = $this->createCacheItem($k, null, false);
            }

            return $cacheItem;
        });

        $cache->method('save')->willReturnCallback(function (CacheItemInterface $i) use (&$cacheItem): bool {
            self::assertInstanceOf(CacheItemInterface::class, $cacheItem);
            static::assertSame($cacheItem->getKey(), $i->getKey());
            $cacheItem = $this->createCacheItem($i->getKey(), $i->get(), true);

            return true;
        });

        $cache->method('deleteItem')->willReturnCallback(function (string $k) use (&$cacheItem): bool {
            static::assertNotNull($cacheItem, 'Was not locked');
            static::assertSame($cacheItem->getKey(), $k);
            static::assertTrue($cacheItem->isHit(), 'Was not locked');

            return true;
        });

        $exporter = new SitemapExporter(
            [],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class)
        );

        $result = $exporter->generate($this->context);

        static::assertTrue($result->isFinish());
    }

    /**
     * NEXT-21735
     */
    #[Group('not-deterministic')]
    public function testWriteWithMultipleSchemesAndSameLanguage(): void
    {
        $salesChannel = $this->salesChannelRepository->search(
            $this->storefrontSalesChannelCriteria([$this->context->getSalesChannelId()]),
            $this->context->getContext()
        )->getEntities()->first();
        static::assertNotNull($salesChannel);

        $domain = $salesChannel->getDomains()?->first();
        static::assertNotNull($domain);

        $this->salesChannelRepository->update([
            [
                'id' => $this->context->getSalesChannelId(),
                'domains' => [
                    [
                        'id' => Uuid::randomHex(),
                        'languageId' => $domain->getLanguageId(),
                        'url' => str_replace('http://', 'https://', (string) $domain->getUrl()),
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $domain->getSnippetSetId(),
                    ],
                ],
            ],
        ], $this->context->getContext());

        $salesChannel = $this->salesChannelRepository->search(
            $this->storefrontSalesChannelCriteria([$this->context->getSalesChannelId()]),
            $this->context->getContext()
        )->getEntities()->first();
        static::assertNotNull($salesChannel);

        $domains = $salesChannel->getDomains();
        static::assertNotNull($domains);
        $languageIds = $domains->map(fn (SalesChannelDomainEntity $salesChannelDomain) => $salesChannelDomain->getLanguageId());

        $languageIds = array_unique($languageIds);

        foreach ($languageIds as $languageId) {
            $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
                ->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);

            $this->generateSitemap($salesChannelContext, false);

            $files = $this->getFilesystem('shopware.filesystem.sitemap')
                ->listContents('sitemap/salesChannel-' . $salesChannel->getId() . '-' . $salesChannelContext->getLanguageId());

            static::assertCount(1, iterator_to_array($files));
        }
    }

    public function testGenerationWithSlashes(): void
    {
        $url1 = new Url();
        $url1->setLoc('/test-with-slash');
        $url1->setLastmod(new \DateTime());
        $url1->setChangefreq('daily');

        $url2 = new Url();
        $url2->setLoc('test-without-slash');
        $url2->setLastmod(new \DateTime());
        $url2->setChangefreq('daily');

        $urls = [$url1, $url2];

        $handler = $this->createMock(AbstractUrlProvider::class);
        $handler->expects(static::once())->method('getUrls')->willReturn(new UrlResult($urls, null));

        $factory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandleMock = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandleMock->expects(static::once())->method('write')->willReturnCallback(function (array $urls): void {
            static::assertCount(2, $urls);
            static::assertInstanceOf(Url::class, $urls[0]);
            static::assertInstanceOf(Url::class, $urls[1]);
            static::assertSame('https://test.com/de/test-with-slash', $urls[0]->getLoc());
            static::assertSame('https://test.com/de/test-without-slash', $urls[1]->getLoc());
        });

        $factory->expects(static::once())->method('create')->willReturn($sitemapHandleMock);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, false));

        $exporter = new SitemapExporter(
            [$handler],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $factory,
            $this->createMock(EventDispatcher::class)
        );

        $salesChannel = Generator::createSalesChannelContext();
        $salesChannel->getSalesChannel()->setDomains(new SalesChannelDomainCollection([
            (new SalesChannelDomainEntity())->assign(['id' => '11', 'url' => 'https://test.com/de', 'languageId' => Defaults::LANGUAGE_SYSTEM]),
        ]));

        $exporter->generate($salesChannel);
    }

    private function createCacheItem(string $key, ?bool $value, ?bool $isHit): CacheItemInterface
    {
        $class = new \ReflectionClass(CacheItem::class);
        $keyProp = $class->getProperty('key');
        $keyProp->setAccessible(true);

        $valueProp = $class->getProperty('value');
        $valueProp->setAccessible(true);

        $isHitProp = $class->getProperty('isHit');
        $isHitProp->setAccessible(true);

        $item = new CacheItem();
        $keyProp->setValue($item, $key);
        $valueProp->setValue($item, $value);
        $isHitProp->setValue($item, $isHit);

        return $item;
    }

    /**
     * @param list<string> $ids
     */
    private function storefrontSalesChannelCriteria(array $ids): Criteria
    {
        $criteria = new Criteria($ids);
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('domains.id', null)]
        ));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $criteria;
    }

    private function generateSitemap(
        SalesChannelContext $salesChannelContext,
        bool $force,
        ?string $lastProvider = null,
        ?int $offset = null
    ): void {
        $result = $this->getContainer()->get(SitemapExporter::class)->generate($salesChannelContext, $force, $lastProvider, $offset);
        if (!$result->isFinish()) {
            $this->generateSitemap($salesChannelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }
}
