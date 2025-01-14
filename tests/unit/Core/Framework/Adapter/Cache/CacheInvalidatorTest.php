<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
            new NullLogger(),
            new RequestStack([new Request()]),
            'test'
        );

        $invalidator->invalidate([]);
    }

    public function testForceInvalidation(): void
    {
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
            0,
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            'prod'
        );

        $invalidator->invalidate(['foo'], true);
    }

    public function testInvalidationIsImplicitlyForcedOnTestEnvs(): void
    {
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
            0,
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            'test'
        );

        $invalidator->invalidate(['foo']);
    }

    public function testInvalidationIsImplicitlyForcedWhenRequestHeaderIsSet(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::never())
            ->method('store');

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE, '1');

        $invalidator = new CacheInvalidator(
            0,
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([$request]),
            'prod'
        );

        $invalidator->invalidate(['foo']);
    }

    public function testStoreInvalidation(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects(static::never())
            ->method('invalidateTags');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects(static::once())
            ->method('store');

        $invalidator = new CacheInvalidator(
            1,
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            'prod'
        );

        $invalidator->invalidate(['foo']);
    }

    #[DataProvider('dataProviderInvalidation')]
    #[DisabledFeatures(['cache_rework'])]
    /**
     * @deprecated tag:v6.7.0 - can be removed as it tests only deprecated functionality
     */
    public function testInvalidation(bool $enableDelay, bool $directInvalidate, bool $backgroundInvalidate, bool $force): void
    {
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
            new NullLogger(),
            new RequestStack([new Request()]),
            'prod'
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
            new NullLogger(),
            new RequestStack([new Request()]),
            'test'
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
            new NullLogger(),
            new RequestStack([new Request()]),
            'test'
        );

        $invalidator->invalidateExpired();
    }
}
