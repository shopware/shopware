<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group cache
 */
class CacheIdLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var object|CacheIdLoader|null
     */
    private $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = $this->getContainer()->get(CacheIdLoader::class);
    }

    public function testLoadExisting(): void
    {
        $id = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchColumn')
            ->willReturn($id);

        $loader = new CacheIdLoader($connection);

        static::assertSame($id, $loader->load());
    }

    public function testMissingCacheIdWritesId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchColumn')
            ->willReturn(false);

        $connection
            ->expects(static::once())
            ->method('executeUpdate');

        $loader = new CacheIdLoader($connection);

        static::assertIsString($loader->load());
    }

    public function testCacheIdIsNotAString(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchColumn')
            ->willReturn(0);

        $connection
            ->expects(static::once())
            ->method('executeUpdate');

        $loader = new CacheIdLoader($connection);

        static::assertIsString($loader->load());
    }

    public function testCacheIdIsLoadedFromDatabase(): void
    {
        $old = $this->loader->load();
        static::assertIsString($old);

        $new = Uuid::randomHex();
        $this->getContainer()->get(Connection::class)
            ->executeUpdate(
                'REPLACE INTO app_config (`key`, `value`) VALUES (:key, :cacheId)',
                ['cacheId' => $new, 'key' => 'cache-id']
            );

        static::assertSame($new, $this->loader->load());

        $this->loader->write($old);

        static::assertSame($old, $this->loader->load());
    }
}
