<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

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
        /*
         * The migration was moved from the core into the storefront bundle.
         */
        $connection->query('DROP TABLE IF EXISTS `seo_url`');

        $sql = <<<SQL
            CREATE TABLE `seo_url` (
              `id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `foreign_key` BINARY(16) NOT NULL,
              `route_name` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `path_info` VARCHAR(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
              `seo_path_info` VARCHAR(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
              `is_canonical` TINYINT(1) NOT NULL DEFAULT 0,
              `is_modified` TINYINT(1) NOT NULL DEFAULT 0,
              `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
              `is_valid` TINYINT(1) NOT NULL DEFAULT 1,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.seo_path_info` (`sales_channel_id`, `seo_path_info`),
              INDEX `idx.path_info` (`sales_channel_id`, `path_info`),
              INDEX `idx.foreign_key` (`sales_channel_id`, `foreign_key`),
              INDEX `idx.auto_increment` (`auto_increment`),
              CONSTRAINT `json.seo_url.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.seo_url.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
