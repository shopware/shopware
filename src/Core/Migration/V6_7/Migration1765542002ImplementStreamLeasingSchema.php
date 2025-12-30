<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765542002ImplementStreamLeasingSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765542002;
    }

    public function update(Connection $connection): void
    {
        // Create webhook_stream table for partition-based locking
        $connection->executeStatement(
            <<<SQL
            CREATE TABLE IF NOT EXISTS `webhook_stream` (
                `partition_key` BINARY(8) NOT NULL,
                `last_processed_sequence_id` BIGINT UNSIGNED DEFAULT 0,
                `locked_by` VARCHAR(255) NULL,
                `lock_expires_at` DATETIME(3) NULL,
                `status` ENUM('HEALTHY', 'SICK') DEFAULT 'HEALTHY',
                `error_count` INT UNSIGNED DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`partition_key`),
                INDEX `idx_webhook_stream_locked_by` (`locked_by`),
                INDEX `idx_webhook_stream_lock_expires_at` (`lock_expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
