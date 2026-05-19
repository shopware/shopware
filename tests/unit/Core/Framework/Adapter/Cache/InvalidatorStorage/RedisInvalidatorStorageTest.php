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

        $redis->expects($this->once())
            ->method('sMembers')
            ->with('invalidation')
            ->willReturn($redis);

        $redis->expects($this->once())
            ->method('del')
            ->with('invalidation')
            ->willReturn($redis);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(false);

        $redis->expects($this->exactly(2))
            ->method('sPop')
            ->with('invalidation', 10000)
            ->willReturnOnConsecutiveCalls(['tag1', 'tag2'], []);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Redis transaction failed (exec returned false), falling back to sequential execution.');

        $storage = new RedisInvalidatorStorage($redis, $logger);

        static::assertSame(['tag1', 'tag2'], $storage->loadAndDelete());
    }

    public function testLoadAndDeleteFallbackOnTransactionException(): void
    {
        $redis = $this->createMock(\Redis::class);

        $redis->method('multi')
            ->willThrowException(new \RedisException('Redis OOM'));

        $redis->expects($this->exactly(2))
            ->method('sPop')
            ->with('invalidation', 10000)
            ->willReturnOnConsecutiveCalls(['tag1', 'tag2'], []);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Redis transaction failed, falling back to sequential execution. Error: Redis OOM');

        $storage = new RedisInvalidatorStorage($redis, $logger);

        static::assertSame(['tag1', 'tag2'], $storage->loadAndDelete());
    }

    public function testLoadAndDeleteFallbackFailure(): void
    {
        $redis = $this->createMock(\Redis::class);

        $redis->method('multi')->willReturn($redis);

        $redis->expects($this->once())
            ->method('sMembers')
            ->willReturn($redis);

        $redis->expects($this->once())
            ->method('del')
            ->willReturn($redis);

        $redis->expects($this->once())
            ->method('exec')
            ->willReturn(false);

        $redis->expects($this->once())
            ->method('sPop')
            ->with('invalidation', 10000)
            ->willThrowException(new \RedisException('Redis is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Redis transaction failed (exec returned false), falling back to sequential execution.');

        $logger->expects($this->once())
            ->method('error')
            ->with('Sequential fallback: Could not load and delete tags from Redis. Error: Redis is down');

        $storage = new RedisInvalidatorStorage($redis, $logger);

        $this->expectException(\RedisException::class);
        $this->expectExceptionMessage('Redis is down');

        $storage->loadAndDelete();
    }
}
