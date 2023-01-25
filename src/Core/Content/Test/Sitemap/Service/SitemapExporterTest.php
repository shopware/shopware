<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporter;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('sales-channel')]
class SitemapExporterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private SalesChannelContext $context;

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

        $result = $exporter->generate($this->context, false, null, null);

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
        $exporter->generate($this->context, false, null, null);
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

        $result = $exporter->generate($this->context, true, null, null);

        static::assertTrue($result->isFinish());
    }

    public function testLocksAndUnlocks(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        /**
         * @var CacheItemInterface $cacheItem
         */
        $cacheItem = null;
        $cache->method('getItem')->willReturnCallback(function (string $k) use (&$cacheItem) {
            if ($cacheItem === null) {
                $cacheItem = $this->createCacheItem($k, null, false);
            }

            return $cacheItem;
        });

        $cache->method('save')->willReturnCallback(function (CacheItemInterface $i) use (&$cacheItem): bool {
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

        $result = $exporter->generate($this->context, false, null, null);

        static::assertTrue($result->isFinish());
    }

    /**
     * NEXT-21735
     *
     * @group not-deterministic
     */
    public function testWriteWithMulitpleSchemesAndSameLanguage(): void
    {
        $salesChannel = $this->salesChannelRepository->search(
            $this->storefontSalesChannelCriteria([$this->context->getSalesChannelId()]),
            $this->context->getContext()
        )->first();

        $domain = $salesChannel->getDomains()->first();

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

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($this->storefontSalesChannelCriteria([$this->context->getSalesChannelId()]), $this->context->getContext())->first();

        $languageIds = $salesChannel->getDomains()->map(fn (SalesChannelDomainEntity $salesChannelDomain) => $salesChannelDomain->getLanguageId());

        $languageIds = array_unique($languageIds);

        foreach ($languageIds as $languageId) {
            $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $languageId]);

            $this->generateSitemap($salesChannelContext, false);

            $files = $this->getFilesystem('shopware.filesystem.sitemap')->listContents('sitemap/salesChannel-' . $salesChannel->getId() . '-' . $salesChannelContext->getLanguageId());

            static::assertCount(1, $files);
        }

        static::assertTrue(true);
    }

    private function createCacheItem($key, $value, $isHit): CacheItemInterface
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

    private function storefontSalesChannelCriteria(array $ids): Criteria
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

    private function generateSitemap(SalesChannelContext $salesChannelContext, bool $force, ?string $lastProvider = null, ?int $offset = null): void
    {
        $result = $this->getContainer()->get(SitemapExporter::class)->generate($salesChannelContext, $force, $lastProvider, $offset);
        if ($result->isFinish() === false) {
            $this->generateSitemap($salesChannelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }
}
