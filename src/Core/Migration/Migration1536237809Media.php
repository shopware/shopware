<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237809Media extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237809;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `media` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `catalog_tenant_id` binary(16) NOT NULL,
              `user_id` binary(16) DEFAULT NULL,
              `user_tenant_id` binary(16) DEFAULT NULL,
              `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci NULL,
              `file_extension` varchar(50) COLLATE utf8mb4_unicode_ci NULL,
              `file_size` int(10) unsigned NULL,
              `meta_data` LONGTEXT DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               CHECK (JSON_VALID(`meta_data`)),
               PRIMARY KEY (`id`, `version_id`, `tenant_id`),
               CONSTRAINT `fk_media.user_id` FOREIGN KEY (`user_id`, `user_tenant_id`) REFERENCES `user` (`id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
               CONSTRAINT `fk_media.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
