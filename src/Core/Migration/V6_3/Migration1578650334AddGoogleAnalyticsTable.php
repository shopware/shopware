<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1578650334AddGoogleAnalyticsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578650334;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `sales_channel_analytics` (
                `id` BINARY(16)  NOT NULL,
                `tracking_id` VARCHAR(50) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT '0',
                `track_orders` TINYINT(1) NOT NULL DEFAULT '0',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $connection->executeUpdate(
            <<<'SQL'
            ALTER TABLE `sales_channel`
            ADD `analytics_id` binary(16) NULL AFTER `payment_method_ids`,
            ADD CONSTRAINT `fk.sales_channel.analytics_id` FOREIGN KEY (`analytics_id`) REFERENCES `sales_channel_analytics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
