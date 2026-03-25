<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1758018342ProductContentLayout extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758018342;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `product_content_layout` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `content_layout_id` BINARY(16) NOT NULL,
                `parameter_bindings` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.product_content_layout.prod_sc` (`product_id`, `sales_channel_id`),
                CONSTRAINT `fk.product_content_layout.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk.product_content_layout.content_layout_id`
                    FOREIGN KEY (`content_layout_id`)
                    REFERENCES `content_layout` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
