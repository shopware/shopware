<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1652864153ReindexMediaFolders;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1652864153ReindexMediaFolders
 */
class Migration1652864153ReindexMediaFoldersTest extends TestCase
{
    use MigrationTestTrait;
    use EnvTestBehaviour;

    private Connection $connection;

    private IndexerQueuer $queuer;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->queuer = new IndexerQueuer($this->connection);
        // remove the media folder indexer from the queue, if it may be already added by some other migration
        $this->queuer->finishIndexer(['media_folder.indexer']);
    }

    public function testItDoesScheduleChildCountForMediaFolders(): void
    {
        $migration = new Migration1652864153ReindexMediaFolders();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $registeredIndexers = $this->queuer->getIndexers();

        static::assertArrayHasKey('media_folder.indexer', $registeredIndexers);
        static::assertEquals([
            'media_folder.child-count',
        ], $registeredIndexers['media_folder.indexer']);
    }

    public function testItDoesNotScheduleChildCountForMediaFoldersIfInInstallation(): void
    {
        $this->setEnvVars(['SHOPWARE_INSTALL' => '1']);
        $migration = new Migration1652864153ReindexMediaFolders();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $registeredIndexers = $this->queuer->getIndexers();

        static::assertArrayNotHasKey('media_folder.indexer', $registeredIndexers);
    }
}
