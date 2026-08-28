<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775570251AddWebhookTransportTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775570251;
    }

    public function update(Connection $connection): void
    {
        $this->createWebhookDeliveryTable($connection);
        $this->addWebhookEventLogColumns($connection);
        $this->addWebhookStreamTable($connection);
    }

    private function createWebhookDeliveryTable(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'webhook_delivery')) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `webhook_delivery` (
                `id`                   BIGINT UNSIGNED AUTO_INCREMENT,
                `webhook_event_log_id` BINARY(16) NOT NULL,
                `webhook_id`           BINARY(16) NULL,
                `partition_key`        BINARY(16) NOT NULL,
                `delivery_status`      VARCHAR(20) NOT NULL DEFAULT \'queued\',
                `execution_count`      INT UNSIGNED NOT NULL DEFAULT 0,
                `next_retry_at`        DATETIME(3) NULL,
                `last_attempt_at`      DATETIME(3) NULL,
                `created_at`           DATETIME(3) NOT NULL,
                `updated_at`           DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.webhook_delivery.webhook_event_log_id` (`webhook_event_log_id`),
                KEY `idx.webhook_delivery.partition_status_retry` (`partition_key`, `delivery_status`, `next_retry_at`, `id`),
                KEY `idx.webhook_delivery.webhook_status` (`webhook_id`, `delivery_status`),
                CONSTRAINT `fk.webhook_delivery.webhook_event_log_id`
                    FOREIGN KEY (`webhook_event_log_id`) REFERENCES `webhook_event_log` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.webhook_delivery.webhook_id`
                    FOREIGN KEY (`webhook_id`) REFERENCES `webhook` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function addWebhookEventLogColumns(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'webhook_event_log', 'sequence')) {
            $connection->executeStatement('
                ALTER TABLE `webhook_event_log`
                    ADD COLUMN `sequence` BIGINT UNSIGNED NULL
            ');
        }
    }

    private function addWebhookStreamTable(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'webhook_stream')) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `webhook_stream` (
                `id`              BINARY(16) NOT NULL,
                `partition_key`   BINARY(16) NOT NULL,
                `locked_by`       VARCHAR(64) NULL,
                `lock_expires_at` DATETIME(3) NULL,
                `last_claimed_at` DATETIME(3) NULL,
                `created_at`      DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.webhook_stream.partition_key` (`partition_key`),
                KEY `idx.webhook_stream.claim` (`last_claimed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
