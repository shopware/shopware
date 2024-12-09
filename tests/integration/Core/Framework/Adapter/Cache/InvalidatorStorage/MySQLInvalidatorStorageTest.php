<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class MySQLInvalidatorStorageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MySQLInvalidatorStorage $storage;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->storage = new MySQLInvalidatorStorage($this->connection);
    }

    protected function tearDown(): void
    {
        parent::setUp();

        $this->connection->executeStatement('DELETE FROM invalidation_tags');
    }

    public function testStoreSingleTag(): void
    {
        $this->storage->store(['tag1']);
        $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');

        static::assertSame(['tag1'], $result);
    }

    public function testStoreMultipleTags(): void
    {
        $this->storage->store(['tag1', 'tag2', 'tag3']);
        $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');

        static::assertSame(['tag1', 'tag2', 'tag3'], $result);
    }

    public function testStoreNoTags(): void
    {
        $this->storage->store([]);
        $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');

        static::assertEmpty($result);
    }

    public function testLoadAndDeleteSingleTag(): void
    {
        $this->storage->store(['tag1']);
        $result = $this->storage->loadAndDelete();

        static::assertSame(['tag1'], $result);
        $remaining = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');
        static::assertEmpty($remaining);
    }

    public function testLoadAndDeleteMultipleTags(): void
    {
        $this->storage->store(['tag1', 'tag2']);
        $result = $this->storage->loadAndDelete();

        static::assertSame(['tag1', 'tag2'], $result);
        $remaining = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');
        static::assertEmpty($remaining);
    }

    public function testLoadAndDeleteWhenEmpty(): void
    {
        $result = $this->storage->loadAndDelete();
        static::assertEmpty($result);
    }

    public function testStoreDuplicateTags(): void
    {
        $this->storage->store(['tag1', 'tag1', 'tag2']);
        $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');

        static::assertSame(['tag1', 'tag2'], $result);
    }
}
