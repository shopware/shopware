<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1792138257AddMaintenancePasswordToSalesChannel;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1792138257AddMaintenancePasswordToSalesChannel::class)]
class Migration1792138257AddMaintenancePasswordToSalesChannelTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1792138257AddMaintenancePasswordToSalesChannel $migration;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->migration = new Migration1792138257AddMaintenancePasswordToSalesChannel();
    }

    #[After]
    public function restoreColumn(): void
    {
        // make sure the new column exists again for the remaining test suite
        $this->migration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792138257, $this->migration->getCreationTimestamp());
    }

    public function testMigrationAddsColumn(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_password'));

        // running twice must be idempotent
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_password'));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_password')) {
            $this->connection->executeStatement(<<<'SQL'
                ALTER TABLE `sales_channel` DROP COLUMN `maintenance_password`
            SQL);
        }
    }
}
