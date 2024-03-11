<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1697462064FixMediaPath;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1697462064FixMediaPath::class)]
class Migration1697462064FixMediaPathTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $migration = new Migration1697462064FixMediaPath();

        $queue = new MultiInsertQueryQueue($this->connection);

        $ids = new IdsCollection();

        $queue->addInsert('media', [
            'id' => $ids->getBytes('empty-file-id'),
            'file_name' => '',
            'path' => 'media/broken/',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('media', [
            'id' => $ids->getBytes('null-file-id'),
            'file_name' => null,
            'path' => 'media/broken-2/',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('media', [
            'id' => $ids->getBytes('valid-file-id'),
            'file_name' => 'valid-file',
            'path' => 'media/valid/valid-file.txt',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration->update($this->connection);

        $emptyFile = $this->connection->fetchAssociative('SELECT * FROM media WHERE id = :id', ['id' => $ids->getBytes('empty-file-id')]);
        static::assertIsArray($emptyFile);
        static::assertNull($emptyFile['path']);

        $nullFile = $this->connection->fetchAssociative('SELECT * FROM media WHERE id = :id', ['id' => $ids->getBytes('null-file-id')]);
        static::assertIsArray($nullFile);
        static::assertNull($nullFile['path']);

        $validFile = $this->connection->fetchAssociative('SELECT * FROM media WHERE id = :id', ['id' => $ids->getBytes('valid-file-id')]);
        static::assertIsArray($validFile);
        static::assertEquals('media/valid/valid-file.txt', $validFile['path']);
    }

    public function testUpdateWithThumbnails(): void
    {
        $migration = new Migration1697462064FixMediaPath();

        $queue = new MultiInsertQueryQueue($this->connection);

        $ids = new IdsCollection();

        $queue->addInsert('media', [
            'id' => $ids->getBytes('empty-file-id'),
            'file_name' => '',
            'path' => 'media/broken/',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $queue->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('empty-file-id'),
            'media_id' => $ids->getBytes('empty-file-id'),
            'path' => 'thumbnail/broken/',
            'width' => 100,
            'height' => 100,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('media', [
            'id' => $ids->getBytes('null-file-id'),
            'file_name' => null,
            'path' => 'media/broken-2/',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $queue->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('null-file-id'),
            'media_id' => $ids->getBytes('null-file-id'),
            'path' => 'thumbnail/broken-2/',
            'width' => 100,
            'height' => 100,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('media', [
            'id' => $ids->getBytes('valid-file-id'),
            'file_name' => 'valid-file',
            'path' => 'media/valid/valid-file.txt',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $queue->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('valid-file-id'),
            'media_id' => $ids->getBytes('valid-file-id'),
            'path' => 'thumbnail/valid/valid-file.txt',
            'width' => 100,
            'height' => 100,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration->update($this->connection);

        $emptyFile = $this->connection->fetchAssociative('SELECT * FROM media_thumbnail WHERE id = :id', ['id' => $ids->getBytes('empty-file-id')]);
        static::assertIsArray($emptyFile);
        static::assertNull($emptyFile['path']);

        $nullFile = $this->connection->fetchAssociative('SELECT * FROM media_thumbnail WHERE id = :id', ['id' => $ids->getBytes('null-file-id')]);
        static::assertIsArray($nullFile);
        static::assertNull($nullFile['path']);

        $validFile = $this->connection->fetchAssociative('SELECT * FROM media_thumbnail WHERE id = :id', ['id' => $ids->getBytes('valid-file-id')]);
        static::assertIsArray($validFile);
        static::assertEquals('thumbnail/valid/valid-file.txt', $validFile['path']);
    }
}
