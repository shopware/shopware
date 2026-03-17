<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\CachedContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(CachedContentSystemDataLoaderTypeResolver::class)]
class CachedContentSystemDataLoaderTypeResolverTest extends TestCase
{
    private CachedContentSystemDataLoaderTypeResolver $cached;

    private AbstractContentSystemDataLoaderTypeResolver&MockObject $inner;

    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(AbstractContentSystemDataLoaderTypeResolver::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cached = new CachedContentSystemDataLoaderTypeResolver($this->inner, $this->cache);
    }

    #[TestDox('returns cached map when cache is populated')]
    public function testResolveReturnsCachedResult(): void
    {
        $expected = new ContentSystemDataLoaderTypeMap([]);

        $this->cache->expects($this->once())
            ->method('get')
            ->with(CachedContentSystemDataLoaderTypeResolver::CACHE_KEY)
            ->willReturn($expected);

        static::assertSame($expected, $this->cached->resolve());
    }

    #[TestDox('delegates to inner service on cache miss')]
    public function testResolveDelegatesToInnerOnCacheMiss(): void
    {
        $expected = new ContentSystemDataLoaderTypeMap([]);

        $this->inner->expects($this->once())
            ->method('resolve')
            ->willReturn($expected);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn (string $key, callable $callback) => $callback());

        static::assertSame($expected, $this->cached->resolve());
    }
}
