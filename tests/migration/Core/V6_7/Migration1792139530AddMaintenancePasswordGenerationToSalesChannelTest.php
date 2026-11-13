<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1792139530AddMaintenancePasswordGenerationToSalesChannel;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1792139530AddMaintenancePasswordGenerationToSalesChannel::class)]
class Migration1792139530AddMaintenancePasswordGenerationToSalesChannelTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1792139530AddMaintenancePasswordGenerationToSalesChannel $migration;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->migration = new Migration1792139530AddMaintenancePasswordGenerationToSalesChannel();
    }

    #[After]
    public function restoreColumn(): void
    {
        // make sure the new column exists again for the remaining test suite
        $this->migration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792139530, $this->migration->getCreationTimestamp());
    }

    public function testMigrationAddsColumn(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_password_generation'));

        // running twice must be idempotent
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_password_generation'));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_password_generation')) {
            $this->connection->executeStatement(<<<'SQL'
                ALTER TABLE `sales_channel` DROP COLUMN `maintenance_password_generation`
            SQL);
        }
    }
}
