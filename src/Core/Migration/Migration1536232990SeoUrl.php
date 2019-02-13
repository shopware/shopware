<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232990SeoUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232990;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `seo_url` (
              `id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `name` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `foreign_key` BINARY(16) NOT NULL,
              `path_info` VARCHAR(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
              `seo_path_info` VARCHAR(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
              `is_canonical` TINYINT(1) NOT NULL DEFAULT 0,
              `is_modified` TINYINT(1) NOT NULL DEFAULT 0,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.seo_routing` (`sales_channel_id`, `seo_path_info`),
              INDEX `idx.entity_canonical_url` (`sales_channel_id`, `foreign_key`, `name`, `is_canonical`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.seo_url.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
