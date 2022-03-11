<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1646125417AddPathFieldToMediaFolder;

class Migration1646125417AddPathFieldToMediaFolderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationCanExecuteMultipleTimes(): void
    {
        // Rollback Migration
        $this->connection->rollBack();
        $this->connection->executeStatement('ALTER TABLE `media_folder` DROP COLUMN `path`');

        $migration = new Migration1646125417AddPathFieldToMediaFolder();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->connection->beginTransaction();

        $columns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `media_folder`'), 'Field');

        static::assertContains('path', $columns);
    }
}
