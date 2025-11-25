<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\InvalidatorStorage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Test\Stub\Redis\RedisStub;

/**
 * @internal
 */
#[CoversClass(RedisInvalidatorStorage::class)]
class RedisInvalidatorStorageTest extends TestCase
{
    public function testStorage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $storage = new RedisInvalidatorStorage(new RedisStub(), $logger);

        static::assertSame($storage->loadAndDelete(), []);

        $storage->store(['foo', 'bar']);

        static::assertSame(['bar', 'foo'], $storage->loadAndDelete());
        static::assertSame([], $storage->loadAndDelete());
    }

    public function testLoadAndDeleteFallbackOnTransactionFailure(): void
    {
        $redis = $this->createMock(\Redis::class);

        $redis->method('multi')->willReturn($redis);

        $redis->expects($this->exactly(2))
            ->method('sMembers')
            ->with('invalidation')
            ->willReturnOnConsecutiveCalls($redis, ['tag1', 'tag2']);

        $redis->expects($this->exactly(2))
            ->method('del')
            ->with('invalidation')
            ->willReturnOnConsecutiveCalls($redis, 1);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Redis transaction failed (exec returned false), falling back to sequential execution.');

        $storage = new RedisInvalidatorStorage($redis, $logger);

        static::assertSame(['tag1', 'tag2'], $storage->loadAndDelete());
    }

    public function testLoadAndDeleteFallbackFailure(): void
    {
        $redis = $this->createMock(\Redis::class);

        $redis->method('multi')->willReturn($redis);
        // First call (in transaction) returns $redis, second call (fallback) throws exception
        $redis->expects($this->exactly(2))
            ->method('sMembers')
            ->willReturnOnConsecutiveCalls(
                $redis,
                static::throwException(new \RedisException('Redis is down'))
            );

        // del is called in transaction (returns redis)
        $redis->expects($this->once())
            ->method('del')
            ->willReturn($redis);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Redis transaction failed (exec returned false), falling back to sequential execution.');

        $logger->expects($this->once())
            ->method('error')
            ->with('Could not load and delete tags from Redis. Error: Redis is down');

        $storage = new RedisInvalidatorStorage($redis, $logger);

        $this->expectException(\RedisException::class);
        $this->expectExceptionMessage('Redis is down');

        $storage->loadAndDelete();
    }
}
