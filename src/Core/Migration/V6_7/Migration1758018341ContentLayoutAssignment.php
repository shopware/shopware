<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1758018341ContentLayoutAssignment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758018341;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `content_layout_assignment` (
                `id` BINARY(16) NOT NULL,
                `entity_type` VARCHAR(50) NULL,
                `entity_id` BINARY(16) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `layout_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.content_layout_assignment.entity_sales_channel` (`entity_type`, `entity_id`, `sales_channel_id`),
                INDEX `idx.content_layout_assignment.sales_channel` (`sales_channel_id`),
                CONSTRAINT `fk.content_layout_assignment.layout_id` FOREIGN KEY (`layout_id`)
                    REFERENCES `content_layout` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.content_layout_assignment.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($sql);

        // Junction table for many-to-many relationship between routes and sales channels
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `content_route_sales_channel` (
                `content_route_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`content_route_id`, `sales_channel_id`),
                INDEX `idx.content_route_sales_channel.sales_channel` (`sales_channel_id`),
                CONSTRAINT `fk.content_route_sales_channel.route_id` FOREIGN KEY (`content_route_id`)
                    REFERENCES `content_route` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.content_route_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
