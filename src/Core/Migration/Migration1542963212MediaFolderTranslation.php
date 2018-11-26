<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542963212MediaFolderTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542963212;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder_translation` (
              `media_folder_id` BINARY(16) NOT NULL,
              `media_folder_version_id`BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255),
              `created_at` DATETIME(3),
              `updated_at` DATETIME(3),
              PRIMARY KEY (`media_folder_id`, `media_folder_version_id`, `language_id`),
              CONSTRAINT FOREIGN KEY (`media_folder_id`, `media_folder_version_id`) REFERENCES `media_folder` (`id`, `version_id`) ON DELETE CASCADE ,
              CONSTRAINT FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
