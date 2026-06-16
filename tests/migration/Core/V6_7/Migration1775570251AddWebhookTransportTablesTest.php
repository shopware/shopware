<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1775570251AddWebhookTransportTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1775570251AddWebhookTransportTables::class)]
class Migration1775570251AddWebhookTransportTablesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1775570251AddWebhookTransportTables();
        static::assertSame(1775570251, $migration->getCreationTimestamp());
    }

    public function testMigrationCreatesWebhookDeliveryTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'webhook_delivery'));

        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'webhook_delivery'));
    }

    public function testMigrationCreatesWebhookStreamTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'webhook_stream'));

        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'webhook_stream'));
    }

    public function testMigrationAddsSequenceColumnToWebhookEventLog(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::columnExists($this->connection, 'webhook_event_log', 'sequence'));

        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_event_log', 'sequence'));
    }

    public function testWebhookDeliveryTableHasExpectedColumns(): void
    {
        $this->rollback();

        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'webhook_event_log_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'webhook_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'partition_key'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'delivery_status'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'execution_count'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'next_retry_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'last_attempt_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_delivery', 'updated_at'));
    }

    public function testWebhookStreamTableHasExpectedColumns(): void
    {
        $this->rollback();

        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_stream', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_stream', 'partition_key'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_stream', 'locked_by'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_stream', 'lock_expires_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_stream', 'last_claimed_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_stream', 'created_at'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'webhook_delivery'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'webhook_stream'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_event_log', 'sequence'));
    }

    public function testUpdateDestructiveDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        $migration = new Migration1775570251AddWebhookTransportTables();
        $migration->updateDestructive($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `webhook_stream`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `webhook_delivery`');

        if (TableHelper::columnExists($this->connection, 'webhook_event_log', 'sequence')) {
            $this->connection->executeStatement('ALTER TABLE `webhook_event_log` DROP COLUMN `sequence`');
        }
    }
}
