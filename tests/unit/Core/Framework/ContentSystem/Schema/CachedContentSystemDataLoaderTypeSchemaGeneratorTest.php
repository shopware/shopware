<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeSchemaGenerator;
use Shopware\Core\Framework\ContentSystem\Schema\CachedContentSystemDataLoaderTypeSchemaGenerator;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(CachedContentSystemDataLoaderTypeSchemaGenerator::class)]
class CachedContentSystemDataLoaderTypeSchemaGeneratorTest extends TestCase
{
    private CachedContentSystemDataLoaderTypeSchemaGenerator $cached;

    private AbstractContentSystemDataLoaderTypeSchemaGenerator&MockObject $inner;

    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(AbstractContentSystemDataLoaderTypeSchemaGenerator::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cached = new CachedContentSystemDataLoaderTypeSchemaGenerator($this->inner, $this->cache);
    }

    #[TestDox('returns cached schema when cache is populated')]
    public function testGetSchemaReturnsCachedResult(): void
    {
        $expected = ['sources' => ['navigation' => ['types' => [['className' => 'Foo', 'genericParameters' => []]]]]];

        $this->cache->expects($this->once())
            ->method('get')
            ->with(CachedContentSystemDataLoaderTypeSchemaGenerator::CACHE_KEY)
            ->willReturn($expected);

        static::assertSame($expected, $this->cached->getSchema());
    }

    #[TestDox('delegates to inner service on cache miss')]
    public function testGetSchemaDelegatesToInnerOnCacheMiss(): void
    {
        $expected = ['sources' => ['entity' => ['types' => [['className' => 'Bar', 'genericParameters' => []]]]]];

        $this->inner->expects($this->once())
            ->method('getSchema')
            ->willReturn($expected);

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn (string $key, callable $callback) => $callback());

        static::assertSame($expected, $this->cached->getSchema());
    }
}
