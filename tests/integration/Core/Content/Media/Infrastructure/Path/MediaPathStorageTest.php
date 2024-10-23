<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Infrastructure\Path\SqlMediaPathStorage;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(SqlMediaPathStorage::class)]
class MediaPathStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testStoreMediaPath(): void
    {
        $ids = new IdsCollection();

        $inserts = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $inserts->addInsert('media', [
            'id' => $ids->getBytes('media'),
            'file_name' => 'test-file-1',
            'file_extension' => 'png',
            'created_at' => '2022-01-01',
        ]);

        $inserts->execute();

        $storage = new SqlMediaPathStorage($this->getContainer()->get(Connection::class));

        $storage->media([
            $ids->get('media') => 'test.jpg',
        ]);

        $path = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT path FROM media WHERE id = :id', ['id' => $ids->getBytes('media')]);

        static::assertEquals('test.jpg', $path);
    }

    public function testStoreThumbnailPath(): void
    {
        $ids = new IdsCollection();

        $inserts = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $inserts->addInsert('media', [
            'id' => $ids->getBytes('media'),
            'file_name' => 'test-file-1',
            'file_extension' => 'png',
            'created_at' => '2022-01-01',
        ]);

        $inserts->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('media_thumbnail'),
            'media_id' => $ids->getBytes('media'),
            'width' => 100,
            'height' => 100,
            'created_at' => '2022-01-01',
        ]);

        $inserts->execute();

        $storage = new SqlMediaPathStorage($this->getContainer()->get(Connection::class));

        $storage->thumbnails([
            $ids->get('media_thumbnail') => 'test.jpg',
        ]);

        $path = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT path FROM media_thumbnail WHERE id = :id', ['id' => $ids->getBytes('media_thumbnail')]);

        static::assertEquals('test.jpg', $path);
    }

    public function testEmptyParametersDoesNotTriggerDatabaseQueries(): void
    {
        $statement = $this->createMock(Statement::class);
        $statement->expects(static::never())->method('execute');

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($statement);

        $storage = new SqlMediaPathStorage($this->getContainer()->get(Connection::class));

        $storage->media([]);
        $storage->thumbnails([]);
    }
}
