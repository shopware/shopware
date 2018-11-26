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
              `catalog_id` binary(16) NOT NULL,
              `user_id` binary(16) DEFAULT NULL,
              `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `file_extension` varchar(50) COLLATE utf8mb4_unicode_ci NULL,
              `file_size` int(10) unsigned NULL,
              `meta_data` JSON NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`),
               CONSTRAINT `json.meta_data` CHECK (JSON_VALID(`meta_data`)),
               CONSTRAINT `fk.media.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
               CONSTRAINT `fk.media.catalog_id` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
