<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765542001AddWebhookStreamTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765542001;
    }

    public function update(Connection $connection): void
    {
        // partition_key is BINARY(8) to minimize index size (64-bit hash)
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `webhook_stream` (
                `partition_key` BINARY(8) NOT NULL,
                `locked_by` VARCHAR(255) NULL,
                `lock_expires_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `error_count` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`partition_key`),
                INDEX `idx_webhook_stream_lock_expires_at` (`lock_expires_at`),
                INDEX `idx_webhook_stream_locked_by` (`locked_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
