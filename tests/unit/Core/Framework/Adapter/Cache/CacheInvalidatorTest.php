<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(CacheInvalidator::class)]
#[Group('cache')]
class CacheInvalidatorTest extends TestCase
{
    public function testInvalidateNothingShouldNotCall(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::never())
            ->method('invalidateTags');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            0,
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger()
        );

        $invalidator->invalidate([]);
    }

    public function testDelay(): void
    {
        Feature::skipTestIfInActive('cache_rework', $this);
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::never())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::once())
            ->method('store');

        $invalidator = new CacheInvalidator(
            1,
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger()
        );

        $invalidator->invalidate(['foo']);
    }

    public function testForce(): void
    {
        Feature::skipTestIfInActive('cache_rework', $this);
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            1,
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger()
        );

        $invalidator->invalidate(['foo'], true);
    }

    #[DataProvider('dataProviderInvalidation')]
    public function testInvalidation(bool $enableDelay, bool $directInvalidate, bool $backgroundInvalidate, bool $force): void
    {
        Feature::skipTestIfActive('cache_rework', $this);

        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($directInvalidate ? static::once() : static::never())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($backgroundInvalidate ? static::once() : static::never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            (int) $enableDelay,
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger()
        );

        $invalidator->invalidate(['foo'], $force);
    }

    public static function dataProviderInvalidation(): \Generator
    {
        yield 'no delay' => [
            false,
            true,
            false,
            false,
        ];

        yield 'no delay, with force' => [
            false,
            true,
            false,
            true,
        ];

        yield 'with delay, no force' => [
            true,
            false,
            true,
            false,
        ];

        yield 'with delay, force' => [
            true,
            true,
            false,
            true,
        ];
    }

    public function testInvalidateExpiredEmpty(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::never())
            ->method('invalidateTags');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::once())
            ->method('loadAndDelete')
            ->willReturn([]);

        $invalidator = new CacheInvalidator(
            0,
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger()
        );

        $invalidator->invalidateExpired();
    }

    public function testInvalidateExpired(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::once())
            ->method('loadAndDelete')
            ->willReturn(['foo']);

        $invalidator = new CacheInvalidator(
            0,
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger()
        );

        $invalidator->invalidateExpired();
    }
}
