<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\Framework\Util\Backtrace\Frame;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
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
    use EnvTestBehaviour;

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
            true,
            true,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Purged tags (1).',
                [
                    'tags' => ['foo'],
                    'caller' => (new Frame('Foo', 'a'))->toArray(),
                ]
            );

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true,
            true,
            $this->createBacktraceCollectorMock('Foo', 'a'),
            new NativeClock()
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Purged tags (1).',
                [
                    'tags' => ['foo'],
                    'caller' => (new Frame('Foo', 'a'))->toArray(),
                ]
            );

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            false,
            true,
            $this->createBacktraceCollectorMock('Foo', 'a'),
            new NativeClock()
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([$request]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true,
            false,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
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
            true,
            true,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
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
            false,
            true,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Purged tags (1).',
                [
                    'tags' => ['foo'],
                    'caller' => (new Frame(
                        CacheInvalidationSubscriber::class,
                        'invalidatePropertyFilters'
                    ))->toArray(),
                ]
            );

        $reverseProxyGateway = $this->createMock(AbstractReverseProxyGateway::class);
        $reverseProxyGateway->expects($this->once())->method('flush');

        $invalidator = new CacheInvalidator(
            [
                $tagAwareAdapter,
            ],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            false,
            true,
            $this->createBacktraceCollectorMock(CacheInvalidationSubscriber::class, 'invalidatePropertyFilters'),
            new NativeClock(),
            $reverseProxyGateway,
        );

        $invalidator->invalidateExpired();
    }

    public function testSoftPurge(): void
    {
        $redisInvalidatorStorage = $this->createMock(RedisInvalidatorStorage::class);
        $redisInvalidatorStorage
            ->expects($this->never())
            ->method('store');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Purged tags (1).',
                [
                    'tags' => ['foo'],
                    'caller' => (new Frame(
                        CacheInvalidationSubscriber::class,
                        'invalidatePropertyFilters'
                    ))->toArray(),
                ]
            );

        $clock = new MockClock('2025-06-13 12:00:00');

        $adapter = new ArrayAdapter();
        $invalidator = new CacheInvalidator(
            [],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            new TagAwareAdapter($adapter, $adapter),
            true,
            true,
            true,
            $this->createBacktraceCollectorMock(CacheInvalidationSubscriber::class, 'invalidatePropertyFilters'),
            $clock
        );

        $invalidator->invalidate(['foo'], true);

        static::assertTrue($adapter->hasItem('http_invalidation_foo_timestamp'));

        $itemValue = $adapter->getItem('http_invalidation_foo_timestamp')->get();
        static::assertSame($clock->now()->getTimestamp(), $itemValue);
    }

    public function testInvalidBacktraceHandling(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Purged tags (1).',
                [
                    'tags' => ['foo'],
                    'caller' => null,
                ]
            );

        $adapter = new ArrayAdapter();
        $invalidator = new CacheInvalidator(
            [],
            $this->createMock(RedisInvalidatorStorage::class),
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            new TagAwareAdapter($adapter, $adapter),
            true,
            true,
            true,
            $this->createBacktraceCollectorMock(),
            new NativeClock()
        );

        $invalidator->invalidate(['foo'], true);
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
            true,
            true,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
        );

        $invalidator->invalidate(['foo']);

        static::assertFalse($adapter->hasItem('http_invalidation_foo_timestamp'));
    }

    public function testStoreFailureFallsBackToImmediateInvalidation(): void
    {
        $this->setEnvVars(['CI' => null]);

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
            true,
            true,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
        );

        $invalidator->invalidate(['foo']);
    }

    public function testStoreFailureLogsWarningInCiMode(): void
    {
        $this->setEnvVars(['CI' => '1']);

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
            ->method('warning')
            ->with('Failed to store cache invalidation tags (CI mode; storage may be unavailable), invalidating immediately. Error: Redis connection failed');

        $invalidator = new CacheInvalidator(
            [$tagAwareAdapter],
            $redisInvalidatorStorage,
            new EventDispatcher(),
            $logger,
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            true,
            true,
            $this->createMock(BacktraceCollector::class),
            new NativeClock()
        );

        $invalidator->invalidate(['foo']);
    }

    private function createBacktraceCollectorMock(?string $class = null, ?string $function = null): BacktraceCollector
    {
        $collector = $this->createMock(BacktraceCollector::class);

        $firstFrame = ($class !== null && $function !== null)
            ? new Frame($class, $function)
            : null;

        $collector->expects($this->once())->method('getFirstFrame')->willReturn($firstFrame);

        return $collector;
    }
}
