<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannelDomain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\CachedDomainLoader;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[CoversClass(CachedDomainLoader::class)]
class CachedDomainLoaderTest extends TestCase
{
    public function testLoadUsesCacheAndReturnsDecoratedResult(): void
    {
        $domains = ['key' => ['url' => 'https://example.com']];

        $decorated = $this->createMock(AbstractDomainLoader::class);
        $decorated->expects($this->once())->method('load')->willReturn($domains);

        $cacheItem = $this->createMock(ItemInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('get')
            ->with(CachedDomainLoader::CACHE_KEY)
            ->willReturnCallback(function (string $key, callable $callback) use ($cacheItem) {
                return $callback($cacheItem);
            });

        $loader = new CachedDomainLoader($decorated, $cache);
        $result = $loader->load();

        static::assertSame($domains, $result);
    }

    public function testLoadUncompressesCachedValue(): void
    {
        $domains = ['key' => ['url' => 'https://example.com']];
        $compressed = CacheValueCompressor::compress($domains);

        $decorated = $this->createMock(AbstractDomainLoader::class);
        $decorated->expects($this->never())->method('load');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($compressed);

        $loader = new CachedDomainLoader($decorated, $cache);
        $result = $loader->load();

        static::assertSame($domains, $result);
    }
}
