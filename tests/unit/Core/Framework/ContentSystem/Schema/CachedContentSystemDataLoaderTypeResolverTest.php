<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
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
    private CacheInterface&Stub $cache;

    protected function setUp(): void
    {
        $this->cache = static::createStub(CacheInterface::class);
    }

    #[TestDox('returns cached map when cache is populated')]
    public function testResolveReturnsCachedResult(): void
    {
        $expected = new ContentSystemDataLoaderTypeMap([]);

        $this->cache
            ->method('get')
            ->willReturn($expected);

        $cached = new CachedContentSystemDataLoaderTypeResolver(
            static::createStub(AbstractContentSystemDataLoaderTypeResolver::class),
            $this->cache,
        );

        static::assertSame($expected, $cached->resolve());
    }

    #[TestDox('delegates to inner service on cache miss')]
    public function testResolveDelegatesToInnerOnCacheMiss(): void
    {
        $expected = new ContentSystemDataLoaderTypeMap([]);

        $inner = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $inner->method('resolve')->willReturn($expected);

        $this->cache
            ->method('get')
            ->willReturnCallback(fn (string $key, callable $callback) => $callback());

        $cached = new CachedContentSystemDataLoaderTypeResolver($inner, $this->cache);

        static::assertSame($expected, $cached->resolve());
    }
}
