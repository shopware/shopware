<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
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
            ->expects($this->never())
            ->method('invalidateTags');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true
        );

        $invalidator->invalidate([]);
    }

    public function testForceInvalidation(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true
        );

        $invalidator->invalidate(['foo'], true);
    }

    public function testInvalidationIsImplicitlyForcedOnTestEnvs(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            false
        );

        $invalidator->invalidate(['foo']);
    }

    public function testInvalidationIsImplicitlyForcedWhenRequestHeaderIsSet(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE, '1');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([$request]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true
        );

        $invalidator->invalidate(['foo']);
    }

    public function testStoreInvalidation(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->never())
            ->method('invalidateTags');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->once())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true
        );

        $invalidator->invalidate(['foo']);
    }

    public function testInvalidateExpiredEmpty(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->never())
            ->method('invalidateTags');

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->once())
            ->method('loadAndDelete')
            ->willReturn([]);

        $invalidator = new CacheInvalidator(
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            false
        );

        $invalidator->invalidateExpired();
    }

    public function testInvalidateExpired(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->once())
            ->method('loadAndDelete')
            ->willReturn(['foo']);

        $invalidator = new CacheInvalidator(
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            false
        );

        $invalidator->invalidateExpired();
    }

    public function testSoftPurge(): void
    {
        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $adapter = new ArrayAdapter();
        $invalidator = new CacheInvalidator(
            [],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            new TagAwareAdapter($adapter, $adapter),
            true,
            true
        );

        $invalidator->invalidate(['foo'], true);

        static::assertTrue($adapter->hasItem('http_invalidation_foo_timestamp'));

        $itemValue = $adapter->getItem('http_invalidation_foo_timestamp')->get();
        static::assertIsInt($itemValue);

        static::assertTrue(time() >= $itemValue, 'Timestamp should be set to current time or later');
    }

    public function testSoftPurgeIsSkipped(): void
    {
        $adapter = new ArrayAdapter();

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->once())
            ->method('store');

        $invalidator = new CacheInvalidator(
            [],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            new NullLogger(),
            new RequestStack([new Request()]),
            new TagAwareAdapter($adapter, $adapter),
            false,
            true
        );

        $invalidator->invalidate(['foo']);

        static::assertFalse($adapter->hasItem('http_invalidation_foo_timestamp'));
    }

    public function testStoreFailureFallsBackToImmediateInvalidation(): void
    {
        $tagAwareAdapter = $this->createMock(TagAwareAdapterInterface::class);
        $tagAwareAdapter
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['foo']);

        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->once())
            ->method('store')
            ->willThrowException(new \RuntimeException('Redis connection failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to store cache invalidation tags, invalidating immediately. Error: Redis connection failed');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true
        );

        $invalidator->invalidate(['foo']);
    }
}
