<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237799SeoUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237799;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `seo_url` (
              `id` binary(16) NOT NULL,
              `sales_channel_id` binary(16) NOT NULL,
              `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `foreign_key` binary(16) NOT NULL,
              `path_info` varchar(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
              `seo_path_info` varchar(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
              `is_canonical` tinyint(1) NOT NULL DEFAULT '0',
              `is_modified` tinyint(1) NOT NULL DEFAULT '0',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              INDEX `idx.seo_routing` (`sales_channel_id`, `seo_path_info`),
              INDEX `idx.entity_canonical_url` (`sales_channel_id`, `foreign_key`, `name`, `is_canonical`),
              CONSTRAINT `fk.seo_url.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
