<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\InvalidatorStorage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        $storage = new RedisInvalidatorStorage(new RedisStub());

        static::assertSame($storage->loadAndDelete(), []);

        $storage->store(['foo', 'bar']);

        static::assertSame(['bar', 'foo'], $storage->loadAndDelete());
        static::assertSame([], $storage->loadAndDelete());
    }

    public function testLoadAndDeleteFallbackOnTransactionFailure(): void
    {
        $redis = $this->createMock(\Redis::class);

        $redis->method('multi')->willReturn($redis);

        $redis->expects(static::exactly(2))
            ->method('sMembers')
            ->with('invalidation')
            ->willReturnOnConsecutiveCalls($redis, ['tag1', 'tag2']);

        $redis->expects(static::exactly(2))
            ->method('del')
            ->with('invalidation')
            ->willReturnOnConsecutiveCalls($redis, 1);

        $redis->expects(static::once())
            ->method('exec')
            ->willReturn(false);

        $storage = new RedisInvalidatorStorage($redis);

        static::assertSame(['tag1', 'tag2'], $storage->loadAndDelete());
    }
}
