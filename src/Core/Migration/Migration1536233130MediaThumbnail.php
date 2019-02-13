<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233130MediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233130;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `media_thumbnail` (
              `id` BINARY(16) NOT NULL,
              `media_id` BINARY(16) NOT NULL,
              `width` INT(10) unsigned NOT NULL,
              `height` INT(10) unsigned NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`id`),
               CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
               CONSTRAINT `fk.media_thumbnail.media_id` FOREIGN KEY (`media_id`)
                 REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
