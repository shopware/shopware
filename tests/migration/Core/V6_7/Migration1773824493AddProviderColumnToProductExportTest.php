<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773824493AddProviderColumnToProductExport;

/**
 * @internal
 */
#[CoversClass(Migration1773824493AddProviderColumnToProductExport::class)]
class Migration1773824493AddProviderColumnToProductExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('ALTER TABLE `product_export` DROP COLUMN `provider`;');
        } catch (\Throwable) {
        }
    }

    public function testMigrationAddsProviderColumn(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, 'product_export', 'provider'));

        $migration = new Migration1773824493AddProviderColumnToProductExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_export', 'provider'));
    }
}
