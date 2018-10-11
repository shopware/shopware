<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237811MediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237811;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `media_thumbnail` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `media_id` binary(16) NOT NULL,
              `media_version_id` binary(16) NOT NULL,
              `media_tenant_id` binary(16) NOT NULL,
              `width` int(10) unsigned NOT NULL,
              `height` int(10) unsigned NOT NULL,
              `highDpi` tinyint(1) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`, `tenant_id`),
               CONSTRAINT `fk_media_thumbnail.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
