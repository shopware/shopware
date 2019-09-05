<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1565705280ProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565705280;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_export` (
                `id` BINARY(16) NOT NULL,
                `product_stream_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `sales_channel_domain_id` BINARY(16) NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `access_key` VARCHAR(255) NOT NULL,
                `encoding` VARCHAR(255) NOT NULL,
                `file_format` VARCHAR(255) NOT NULL,
                `include_variants` TINYINT(1) NULL DEFAULT \'0\',
                `generate_by_cronjob` TINYINT(1) NOT NULL DEFAULT \'0\',
                `last_generation` DATETIME(3) NULL,
                `interval` INT(11) NOT NULL,
                `header_template` LONGTEXT NULL,
                `body_template` LONGTEXT NOT NULL,
                `footer_template` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `file_name` (`file_name`),
                KEY `fk.product_export.product_stream_id` (`product_stream_id`),
                KEY `fk.product_export.sales_channel_id` (`sales_channel_id`),
                KEY `fk.product_export.sales_channel_domain_id` (`sales_channel_domain_id`),
                CONSTRAINT `fk.product_export.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_export.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_export.sales_channel_domain_id` FOREIGN KEY (`sales_channel_domain_id`) REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
