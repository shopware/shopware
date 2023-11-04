<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1654839361ProductDownload;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1654839361ProductDownload
 */
class Migration1654839361ProductDownloadTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1654839361ProductDownload();
        $migration->update($this->connection);
        // test it can be executed multiple times
        $migration->update($this->connection);

        $productDownloadColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM product_download'), 'Field');

        static::assertContains('id', $productDownloadColumns);
        static::assertContains('version_id', $productDownloadColumns);
        static::assertContains('product_id', $productDownloadColumns);
        static::assertContains('product_version_id', $productDownloadColumns);
        static::assertContains('position', $productDownloadColumns);
        static::assertContains('media_id', $productDownloadColumns);
        static::assertContains('custom_fields', $productDownloadColumns);
        static::assertContains('created_at', $productDownloadColumns);
        static::assertContains('updated_at', $productDownloadColumns);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'states'));
    }

    private function prepare(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `product_download`');

        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'states')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `states`');
        }
    }
}
