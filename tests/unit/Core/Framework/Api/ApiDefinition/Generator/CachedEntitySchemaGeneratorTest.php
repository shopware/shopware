<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\CachedEntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(CachedEntitySchemaGenerator::class)]
class CachedEntitySchemaGeneratorTest extends TestCase
{
    private CachedEntitySchemaGenerator $cachedEntitySchemaGenerator;

    private CacheInterface&MockObject $cache;

    private EntitySchemaGenerator&MockObject $entitySchemaGenerator;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->entitySchemaGenerator = $this->createMock(EntitySchemaGenerator::class);
        $this->cachedEntitySchemaGenerator = new CachedEntitySchemaGenerator(
            $this->entitySchemaGenerator,
            $this->cache,
        );
    }

    public function testSupportsCallsInnerServiceSupports(): void
    {
        $this->entitySchemaGenerator->expects(static::once())
            ->method('supports')
            ->with('foo')
            ->willReturn(false);

        static::assertFalse($this->cachedEntitySchemaGenerator->supports('foo', ''));
    }

    public function testGenerateCallsInnerServiceGenerate(): void
    {
        $this->entitySchemaGenerator->expects(static::once())
            ->method('generate')
            ->willThrowException(new \RuntimeException());

        static::expectException(\RuntimeException::class);
        $this->cachedEntitySchemaGenerator->generate([], 'api', 'json', null);
    }

    public function testGetSchemaUtilizesCacheIfPresent(): void
    {
        $result = [
            'foo' => [
                'bar' => null,
            ],
        ];

        $this->cache->expects(static::once())
            ->method('get')
            ->willReturn($result);

        static::assertSame($result, $this->cachedEntitySchemaGenerator->getSchema([]));
    }

    public function testGetSchemaCallsInnerWithAbsentCache(): void
    {
        $result = [
            'fiz' => [
                'buz' => null,
            ],
        ];
        $this->entitySchemaGenerator->expects(static::once())
            ->method('getSchema')
            ->willReturn($result);
        $this->cache->expects(static::once())
            ->method('get')
            ->willReturn($this->entitySchemaGenerator->getSchema([]));

        static::assertSame($result, $this->cachedEntitySchemaGenerator->getSchema([]));
    }
}
