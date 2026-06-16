<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1781614585RemoveMaintenanceIpWhitelistFromSalesChannel;

/**
 * @internal
 */
#[CoversClass(Migration1781614585RemoveMaintenanceIpWhitelistFromSalesChannel::class)]
class Migration1781614585RemoveMaintenanceIpWhitelistFromSalesChannelTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    #[After]
    public function restoreColumn(): void
    {
        if (!TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_ip_whitelist')) {
            $this->connection->executeStatement(
                'ALTER TABLE `sales_channel` ADD `maintenance_ip_whitelist` JSON NULL'
            );
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1781614585, (new Migration1781614585RemoveMaintenanceIpWhitelistFromSalesChannel())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesWhitelistColumn(): void
    {
        $migration = new Migration1781614585RemoveMaintenanceIpWhitelistFromSalesChannel();

        // update() must only remove the sync triggers, not the column
        $migration->update($this->connection);
        $migration->update($this->connection);

        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'sales_channel', 'maintenance_ip_whitelist'));
    }
}
