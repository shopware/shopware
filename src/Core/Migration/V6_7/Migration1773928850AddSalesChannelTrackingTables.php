<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1773928850AddSalesChannelTrackingTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773928850;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'sales_channel_tracking_order')) {
            $connection->executeStatement('
                CREATE TABLE `sales_channel_tracking_order` (
                    `id`               BINARY(16)  NOT NULL,
                    `order_id`         BINARY(16)  NOT NULL,
                    `order_version_id` BINARY(16)  NOT NULL,
                    `sales_channel_id` BINARY(16)  NOT NULL,
                    `created_at`       DATETIME(3) NOT NULL,
                    `updated_at`       DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq.sales_channel_tracking_order.order` (`order_id`, `order_version_id`),
                    KEY `idx.sales_channel_tracking_order.sales_channel_id` (`sales_channel_id`),
                    CONSTRAINT `fk.sc_tracking_order.order_id`
                        FOREIGN KEY (`order_id`, `order_version_id`)
                        REFERENCES `order` (`id`, `version_id`)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk.sc_tracking_order.sales_channel_id`
                        FOREIGN KEY (`sales_channel_id`)
                        REFERENCES `sales_channel` (`id`)
                        ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ');
        }

        if (!TableHelper::tableExists($connection, 'sales_channel_tracking_customer')) {
            $connection->executeStatement('
                CREATE TABLE `sales_channel_tracking_customer` (
                    `id`               BINARY(16)  NOT NULL,
                    `customer_id`      BINARY(16)  NOT NULL,
                    `sales_channel_id` BINARY(16)  NOT NULL,
                    `created_at`       DATETIME(3) NOT NULL,
                    `updated_at`       DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq.sales_channel_tracking_customer.customer` (`customer_id`),
                    KEY `idx.sales_channel_tracking_customer.sales_channel_id` (`sales_channel_id`),
                    CONSTRAINT `fk.sc_tracking_customer.customer_id`
                        FOREIGN KEY (`customer_id`)
                        REFERENCES `customer` (`id`)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk.sc_tracking_customer.sales_channel_id`
                        FOREIGN KEY (`sales_channel_id`)
                        REFERENCES `sales_channel` (`id`)
                        ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
