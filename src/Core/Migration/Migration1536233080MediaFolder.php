<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233080MediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233080;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder` (
              `id` BINARY(16) NOT NULL,
              `parent_id` BINARY(16) NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
              `child_count` INT(11) unsigned NOT NULL DEFAULT 0,
              `media_folder_configuration_id` BINARY(16) NULL,
              `use_parent_configuration` TINYINT(1) NULL DEFAULT 1,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.media_folder.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.media_folder.parent_id` FOREIGN KEY (`parent_id`) 
                REFERENCES `media_folder` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
