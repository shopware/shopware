<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773928850AddSalesChannelTrackingTables;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1773928850AddSalesChannelTrackingTables::class)]
class Migration1773928850AddSalesChannelTrackingTablesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        static::assertSame(1773928850, $migration->getCreationTimestamp());
    }

    public function testMigrationCreatesOrderTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'sales_channel_tracking_order'));

        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'sales_channel_tracking_order'));
    }

    public function testMigrationCreatesCustomerTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'sales_channel_tracking_customer'));

        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'sales_channel_tracking_customer'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'sales_channel_tracking_order'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'sales_channel_tracking_customer'));
    }

    public function testOrderTableHasExpectedColumns(): void
    {
        $this->rollback();

        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_order', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_order', 'order_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_order', 'order_version_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_order', 'sales_channel_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_order', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_order', 'updated_at'));
    }

    public function testCustomerTableHasExpectedColumns(): void
    {
        $this->rollback();

        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_customer', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_customer', 'customer_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_customer', 'sales_channel_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_customer', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_tracking_customer', 'updated_at'));
    }

    public function testUpdateDestructiveDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        $migration = new Migration1773928850AddSalesChannelTrackingTables();
        $migration->updateDestructive($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_tracking_order`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_tracking_customer`');
    }
}
