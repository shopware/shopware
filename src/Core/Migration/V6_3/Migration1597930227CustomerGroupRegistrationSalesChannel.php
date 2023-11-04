<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1597930227CustomerGroupRegistrationSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597930227;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `customer_group_registration_sales_channels` (
    `customer_group_id` BINARY(16) NOT NULL,
    `sales_channel_id` BINARY(16) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`customer_group_id`,`sales_channel_id`),
    KEY `fk.customer_group_registration_sales_channels.customer_group_id` (`customer_group_id`),
    KEY `fk.customer_group_registration_sales_channels.sales_channel_id` (`sales_channel_id`),
    CONSTRAINT `fk.customer_group_registration_sales_channels.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.customer_group_registration_sales_channels.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
