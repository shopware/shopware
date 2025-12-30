<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765542000AddOutboxFieldsToWebhookEventLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765542000;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'webhook_event_log', 'sequence')) {
            // Sequence provides a stable FIFO order; the unique index supports fast ordering and prevents duplicates.
            $connection->executeStatement(
                'ALTER TABLE `webhook_event_log` ADD COLUMN `sequence` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE INDEX `uniq.webhook_event_log_sequence` (`sequence`)'
            );
        }

        $this->addColumn(
            connection: $connection,
            table: 'webhook_event_log',
            column: 'execution_count',
            type: 'INT',
            nullable: false,
            default: '0'
        );

        $this->addColumn(
            connection: $connection,
            table: 'webhook_event_log',
            column: 'next_retry_at',
            type: 'DATETIME(3)',
            nullable: true
        );

        $this->addColumn(
            connection: $connection,
            table: 'webhook_event_log',
            column: 'last_attempt_at',
            type: 'DATETIME(3)',
            nullable: true
        );

        if (!$this->indexExists($connection, 'webhook_event_log', 'idx_webhook_event_log_delivery_status_next_retry_at')) {
            $connection->executeStatement('CREATE INDEX `idx_webhook_event_log_delivery_status_next_retry_at` ON `webhook_event_log` (`delivery_status`, `next_retry_at`)');
        }

        // Why BINARY(8)?
        // 1. Storage Efficiency: Fixed 8 bytes vs variable string. Prevents index bloat as InnoDB secondary indexes include the PK.
        // 2. Performance: Numeric byte comparison avoids charset/collation overhead.
        // 3. Stability: Avoids key-length limits (3072 bytes) regardless of partition name length.
        // 4. Algorithm: xxh3 (64-bit) ensures optimal distribution.
        $this->addColumn(
            connection: $connection,
            table: 'webhook_event_log',
            column: 'partition_key',
            type: 'BINARY(8)',
            nullable: true
        );

        // Primary outbox index: Covers fetchQueued, fetchPendingRetries, and EXISTS subqueries
        // WHERE partition_key = ? AND delivery_status = ? ORDER BY sequence ASC
        if (!$this->indexExists($connection, 'webhook_event_log', 'idx_webhook_event_log_partition_delivery_sequence')) {
            $connection->executeStatement('CREATE INDEX `idx_webhook_event_log_partition_delivery_sequence` ON `webhook_event_log` (`partition_key`, `delivery_status`, `sequence`)');
        }

        // Index optimized for Auto-Recovery queries:
        // FROM webhook_event_log
        // WHERE delivery_status IN (:queued, :pending)
        // AND created_at > DATE_SUB(NOW(3), INTERVAL 1 HOUR)
        if (!$this->indexExists($connection, 'webhook_event_log', 'idx_webhook_event_log_execution_status_created_at')) {
            $connection->executeStatement('CREATE INDEX `idx_webhook_event_log_execution_status_created_at` ON `webhook_event_log` (`delivery_status`, `created_at`, `partition_key`)');
        }

        if (!$this->indexExists($connection, 'webhook_event_log', 'idx_webhook_event_log_app_name_sequence')) {
            // Matches partitioned FIFO lookups by app_name with ordering on sequence.
            $connection->executeStatement('CREATE INDEX `idx_webhook_event_log_app_name_sequence` ON `webhook_event_log` (`app_name`, `sequence`)');
        }
    }
}
