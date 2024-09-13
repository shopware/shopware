<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CacheIdLoader::class)]
class CacheIdLoaderTest extends TestCase
{
    private AbstractKeyValueStorage&MockObject $storage;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(AbstractKeyValueStorage::class);
        unset($_SERVER['SHOPWARE_CACHE_ID'], $_ENV['SHOPWARE_CACHE_ID']);
    }

    public function testLoadExisting(): void
    {
        $id = Uuid::randomHex();
        $this->storage->method('get')->willReturn($id);

        $loader = new CacheIdLoader($this->storage);

        static::assertSame($id, $loader->load());
    }

    public function testMissingCacheIdWritesId(): void
    {
        $this->storage->method('get')->willReturn(false);

        $loader = new CacheIdLoader($this->storage);

        static::assertTrue(Uuid::isValid($loader->load()));
    }

    public function testCacheIdIsNotAString(): void
    {
        $this->storage->method('get')->willReturn(0);

        $loader = new CacheIdLoader($this->storage);

        static::assertTrue(Uuid::isValid($loader->load()));
    }
}
