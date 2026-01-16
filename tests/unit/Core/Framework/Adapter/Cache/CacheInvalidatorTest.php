<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Util\BacktraceCollector;
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
            true,
            true,
            'info',
            $this->createMock(BacktraceCollector::class)
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
            ->method('log')
            ->with(
                'debug',
                'Purged 1 tags.',
                [
                    'tags' => ['foo'],
                    'caller' => [
                        'class' => self::class,
                        'function' => __FUNCTION__,
                    ],
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
            'debug',
            $this->createBacktraceCollectorMock(self::class, __FUNCTION__)
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
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                [
                    'tags' => ['foo'],
                    'caller' => [
                        'class' => self::class,
                        'function' => __FUNCTION__,
                    ],
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
            'info',
            $this->createBacktraceCollectorMock(self::class, __FUNCTION__)
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
        $logger->expects($this->never())->method('log');

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
            'none',
            $this->createMock(BacktraceCollector::class)
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
            'info',
            $this->createMock(BacktraceCollector::class)
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
            'info',
            $this->createMock(BacktraceCollector::class)
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
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                [
                    'tags' => ['foo'],
                    'caller' => [
                        'class' => CacheInvalidationSubscriber::class,
                        'function' => 'invalidatePropertyFilters',
                    ],
                ]
            );

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
            'info',
            $this->createBacktraceCollectorMock(CacheInvalidationSubscriber::class, 'invalidatePropertyFilters')
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
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                [
                    'tags' => ['foo'],
                    'caller' => [
                        'class' => CacheInvalidationSubscriber::class,
                        'function' => 'invalidatePropertyFilters',
                    ],
                ]
            );

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
            'info',
            $this->createBacktraceCollectorMock(CacheInvalidationSubscriber::class, 'invalidatePropertyFilters')
        );

        $invalidator->invalidate(['foo'], true);

        static::assertTrue($adapter->hasItem('http_invalidation_foo_timestamp'));

        $itemValue = $adapter->getItem('http_invalidation_foo_timestamp')->get();
        static::assertIsInt($itemValue);

        static::assertTrue(time() >= $itemValue, 'Timestamp should be set to current time or later');
    }

    /**
     * @param array<string, mixed> $backtrace
     */
    #[DataProvider('invalidBacktraceProvider')]
    public function testInvalidBacktraceHandling(array $backtrace): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                [
                    'tags' => ['foo'],
                    'caller' => null,
                ]
            );

        $backtraceCollector = $this->createMock(BacktraceCollector::class);
        $backtraceCollector
            ->expects($this->once())
            ->method('collect')
            ->willReturn($backtrace);

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
            'info',
            $backtraceCollector
        );

        $invalidator->invalidate(['foo'], true);
    }

    /**
     * @return iterable<
     *   string,
     *   array{
     *     backtrace: array{
     *       file?: string,
     *       line?: int,
     *       function?: string,
     *       class?: string
     *     }
     *   }
     * >
     */
    public static function invalidBacktraceProvider(): iterable
    {
        yield 'empty backtrace' => [
            'backtrace' => [],
        ];

        yield 'single frame (ignored)' => [
            'backtrace' => [
                'file' => '',
                'line' => 42,
                'function' => 'invalidate',
                'class' => 'Shopware\Core\Framework\Adapter\Cache\CacheInvalidator',
            ],
        ];

        yield 'frame without class' => [
            'backtrace' => [
                'file' => '',
                'line' => 42,
                'function' => 'invalidate',
            ],
        ];
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
            'info',
            $this->createMock(BacktraceCollector::class)
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
            true,
            true,
            'info',
            $this->createMock(BacktraceCollector::class)
        );

        $invalidator->invalidate(['foo']);
    }

    private function createBacktraceCollectorMock(string $class, string $function): BacktraceCollector
    {
        $collector = $this->createMock(BacktraceCollector::class);
        \assert($collector instanceof BacktraceCollector);

        $collector->expects($this->once())->method('collect')->willReturn([
            [
                'class' => $class,
                'function' => $function,
            ],
        ]);

        return $collector;
    }
}
