<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1663402842AddPathToMedia;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1663402842AddPathToMedia
 */
class Migration1663402842AddPathToMediaTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private IndexerQueuer $queuer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->queuer = new IndexerQueuer($this->connection);
        // remove the media folder indexer from the queue, if it may be already added by some other migration
        $this->queuer->finishIndexer(['media.indexer']);
    }

    public function testTablesHaveFieldPath(): void
    {
        if ($this->columnPathExists('media')) {
            $this->connection->executeStatement('ALTER TABLE `media` DROP COLUMN `path`');
        }

        if ($this->columnPathExists('media_thumbnail')) {
            $this->connection->executeStatement('ALTER TABLE `media_thumbnail` DROP COLUMN `path`');
        }

        static::assertFalse($this->columnPathExists('media'));
        static::assertFalse($this->columnPathExists('media_thumbnail'));

        $migration = new Migration1663402842AddPathToMedia();
        $migration->update($this->connection);

        static::assertTrue($this->columnPathExists('media'));
        static::assertTrue($this->columnPathExists('media_thumbnail'));

        $registeredIndexers = $this->queuer->getIndexers();
        static::assertArrayHasKey('media.indexer', $registeredIndexers);

        // run again to check nothing breaks
        $migration = new Migration1663402842AddPathToMedia();
        $migration->update($this->connection);
    }

    private function columnPathExists(string $table): bool
    {
        $columns = array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field');

        return \in_array('path', $columns, true);
    }
}
