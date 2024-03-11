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
use Shopware\Core\Migration\V6_5\Migration1697532722FixADInMediaPath;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1697532722FixADInMediaPath::class)]
class Migration1697532722FixADInMediaPathTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $migration = new Migration1697532722FixADInMediaPath();

        $queue = new MultiInsertQueryQueue($this->connection);

        $ids = new IdsCollection();

        $queue->addInsert('media', [
            'id' => $ids->getBytes('valid-file-id'),
            'file_name' => 'valid-file',
            'path' => 'media/valid/valid-file.txt',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('media', [
            'id' => $ids->getBytes('file-with-ad'),
            'file_name' => 'ad-file',
            'path' => 'media/ad/ad-file.txt',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration->update($this->connection);

        $validFile = $this->connection->fetchAssociative('SELECT * FROM media WHERE id = :id', ['id' => $ids->getBytes('valid-file-id')]);
        static::assertIsArray($validFile);
        static::assertEquals('media/valid/valid-file.txt', $validFile['path']);

        $adFile = $this->connection->fetchAssociative('SELECT * FROM media WHERE id = :id', ['id' => $ids->getBytes('file-with-ad')]);
        static::assertIsArray($adFile);
        static::assertEquals('media/g0/ad-file.txt', $adFile['path']);
    }

    public function testUpdateWithThumbnails(): void
    {
        $migration = new Migration1697532722FixADInMediaPath();

        $queue = new MultiInsertQueryQueue($this->connection);

        $ids = new IdsCollection();

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

        $queue->addInsert('media', [
            'id' => $ids->getBytes('file-with-ad'),
            'file_name' => 'ad-file',
            'path' => 'media/ad/ad-file.txt',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $queue->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('file-with-ad'),
            'media_id' => $ids->getBytes('file-with-ad'),
            'path' => 'thumbnail/ad/ad-file.txt',
            'width' => 100,
            'height' => 100,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration->update($this->connection);

        $validFile = $this->connection->fetchAssociative('SELECT * FROM media_thumbnail WHERE id = :id', ['id' => $ids->getBytes('valid-file-id')]);
        static::assertIsArray($validFile);
        static::assertEquals('thumbnail/valid/valid-file.txt', $validFile['path']);

        $adFile = $this->connection->fetchAssociative('SELECT * FROM media_thumbnail WHERE id = :id', ['id' => $ids->getBytes('file-with-ad')]);
        static::assertIsArray($adFile);
        static::assertEquals('thumbnail/g0/ad-file.txt', $adFile['path']);
    }
}
