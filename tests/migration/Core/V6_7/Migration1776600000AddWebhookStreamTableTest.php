<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1776600000AddWebhookStreamTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1776600000AddWebhookStreamTable::class)]
class Migration1776600000AddWebhookStreamTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testWebhookStreamTableHasExpectedColumns(): void
    {
        $this->rollback();

        $migration = new Migration1776600000AddWebhookStreamTable();
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
        $migration = new Migration1776600000AddWebhookStreamTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'webhook_stream'));
    }

    public function testBackfillPreservesExistingPartitionKeysByteForByte(): void
    {
        $this->rollback();
        $this->connection->executeStatement('DELETE FROM `webhook_delivery`');

        $partitionA = Hasher::hashBinary('app-a', 'xxh128');
        $partitionB = Hasher::hashBinary('app-b', 'xxh128');

        $this->seedDeliveryRow($partitionA);
        $this->seedDeliveryRow($partitionA); // duplicate partition — must dedupe
        $this->seedDeliveryRow($partitionB);

        (new Migration1776600000AddWebhookStreamTable())->update($this->connection);

        $streamKeys = $this->connection->fetchFirstColumn('SELECT `partition_key` FROM `webhook_stream` ORDER BY `partition_key`');
        $expected = [$partitionA, $partitionB];
        sort($expected);

        static::assertSame($expected, $streamKeys, 'Backfill must preserve partition_key bytes exactly (no re-hash, no encoding drift)');
    }

    private function seedDeliveryRow(string $partitionKey): void
    {
        $eventLogId = Uuid::randomBytes();

        $this->connection->insert('webhook_event_log', [
            'id' => $eventLogId,
            'delivery_status' => 'queued',
            'webhook_name' => 'backfill-test',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'partition_key' => $partitionKey,
            'delivery_status' => 'queued',
            'execution_count' => 0,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `webhook_stream`');
    }
}
