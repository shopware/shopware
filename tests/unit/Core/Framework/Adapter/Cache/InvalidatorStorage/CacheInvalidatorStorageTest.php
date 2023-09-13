<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\InvalidatorStorage;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\CacheInvalidatorStorage;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - This storage is not optimal for atomic operations, please use the RedisInvalidatorStorage instead
 *
 * @covers \Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\CacheInvalidatorStorage
 */
class CacheInvalidatorStorageTest extends TestCase
{
    /**
     * @DisabledFeatures("v6.6.0.0")
     */
    public function testStorage(): void
    {
        $storage = new CacheInvalidatorStorage(new ArrayAdapter());

        static::assertSame($storage->loadAndDelete(), []);

        $storage->store(['foo', 'bar']);

        static::assertSame(['foo', 'bar'], $storage->loadAndDelete());
        static::assertSame([], $storage->loadAndDelete());
    }
}
