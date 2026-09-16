<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1775180400AddNextGenerationAtToProductExport;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1775180400AddNextGenerationAtToProductExport::class)]
class Migration1775180400AddNextGenerationAtToProductExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('ALTER TABLE `product_export` DROP COLUMN `next_generation_at`;');
        } catch (\Throwable) {
        }
    }

    public function testMigrationAddsNextGenerationAtColumn(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, 'product_export', 'next_generation_at'));

        $migration = new Migration1775180400AddNextGenerationAtToProductExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_export', 'next_generation_at'));
    }
}
