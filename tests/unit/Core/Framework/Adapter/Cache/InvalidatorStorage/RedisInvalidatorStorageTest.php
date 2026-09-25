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

    public function testStoreIgnoresTagKeys(): void
    {
        $storage = new RedisInvalidatorStorage(new RedisStub());

        $storage->store([
            'abcdef0123456789abcdef0123456789' => 'cms-page-example',
            'fedcba9876543210fedcba9876543210' => 'product-example',
        ]);

        static::assertSame(['cms-page-example', 'product-example'], $storage->loadAndDelete());
    }

    public function testStoreWithoutTags(): void
    {
        $storage = new RedisInvalidatorStorage(new RedisStub());

        $storage->store([]);

        static::assertSame([], $storage->loadAndDelete());
    }
}
