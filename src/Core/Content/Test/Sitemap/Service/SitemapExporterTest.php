<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporter;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SitemapExporterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'sitemap-exporter-test');
    }

    public function testNotLocked(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, false));

        $exporter = new SitemapExporter(
            [],
            $cache,
            10,
            $this->createMock(FilesystemInterface::class),
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
            $this->createMock(FilesystemInterface::class),
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
            $this->createMock(FilesystemInterface::class),
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

        $cache->method('save')->willReturnCallback(function (CacheItemInterface $i) use (&$cacheItem): void {
            static::assertSame($cacheItem->getKey(), $i->getKey());
            $cacheItem = $this->createCacheItem($i->getKey(), $i->get(), true);
        });

        $cache->method('deleteItem')->willReturnCallback(function (string $k) use (&$cacheItem): void {
            static::assertNotNull($cacheItem, 'Was not locked');
            static::assertSame($cacheItem->getKey(), $k);
            static::assertTrue($cacheItem->isHit(), 'Was not locked');
        });

        $exporter = new SitemapExporter(
            [],
            $cache,
            10,
            $this->createMock(FilesystemInterface::class),
            $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class)
        );

        $result = $exporter->generate($this->context, false, null, null);

        static::assertTrue($result->isFinish());
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
}
