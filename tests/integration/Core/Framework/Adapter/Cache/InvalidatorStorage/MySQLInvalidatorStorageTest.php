<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class MySQLInvalidatorStorageTest extends TestCase
{
    use KernelTestBehaviour;

    private MySQLInvalidatorStorage $storage;

    private Connection $connection;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->storage = new MySQLInvalidatorStorage($this->connection, $this->logger);
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

    public function testLoadAndDeleteOnlyDeletesSelectedItems(): void
    {
        $storage = new MySQLInvalidatorStorage(
            $this->connection,
            $this->logger,
            fn (MySQLInvalidatorStorage $storage, array $tags) => $storage->store(['tag4', 'tag5', 'tag6']),
        );

        // store these first
        $this->storage->store(['tag1', 'tag2', 'tag3']);

        $tags = $storage->loadAndDelete();

        static::assertEquals(['tag1', 'tag2', 'tag3'], $tags);

        $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');

        static::assertSame(['tag4', 'tag5', 'tag6'], $result);
    }

    public function testLoadAndDeleteWithParallelDelete(): void
    {
        // create a separate connection to simulate parallel request
        $connection2 = MySQLFactory::create();
        $storage2 = new MySQLInvalidatorStorage($connection2, $this->logger);

        $storage1 = new MySQLInvalidatorStorage(
            $this->connection,
            $this->logger,
            function () use ($storage2): void {
                // in the middle of the original request running `loadAndDelete`
                // 3. insert some more tags (in parallel worker)
                $storage2->store(['tag4', 'tag5', 'tag6']);
                $storage2->store(['tag7', 'tag8', 'tag9']);

                // 4. now load and delete (in parallel worker)
                // should only load and delete our tags inserted in this process
                // as other tags will be locked by parallel worker (first worker, step 2)
                $delete = $storage2->loadAndDelete();

                static::assertSame(['tag4', 'tag5', 'tag6', 'tag7', 'tag8', 'tag9'], $delete);
                $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');
                static::assertSame(['tag1', 'tag2', 'tag3'], $result);
            },
        );

        // 1. store these first
        $storage1->store(['tag1', 'tag2', 'tag3']);

        // 2. load tags on original connection (which will trigger callable from above to simulate parallel request loading tags)
        $tags = $storage1->loadAndDelete();

        static::assertEquals(['tag1', 'tag2', 'tag3'], $tags);

        $result = $this->connection->fetchFirstColumn('SELECT tag FROM invalidation_tags');

        static::assertSame([], $result);
    }

    public function testLoadAndDeleteExceptionIsCaughtAndLogged(): void
    {
        $this->logger->expects(static::once())->method('warning')
            ->with('Cache tags could not be fetched or removed from storage. Possible deadlock encountered. If the error persists, try the redis adapter. Error: Deadlock');

        $connection = $this->createMock(Connection::class);

        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([['id' => 'id1', 'tag1'], ['id' => 'id2', 'tag2']]);

        $statement = $this->createMock(Statement::class);

        $e = new class('Deadlock') extends \Exception implements RetryableException {};

        $statement
            ->method('executeStatement')
            ->with(['id1', 'id2'])
            ->willThrowException($e);

        $connection->expects(static::once())
            ->method('prepare')
            ->with('DELETE FROM invalidation_tags WHERE id BETWEEN ? AND ?')
            ->willReturn($statement);

        $connection->expects(static::once())
            ->method('transactional')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $storage = new MySQLInvalidatorStorage($connection, $this->logger);
        $storage->loadAndDelete();
    }
}
