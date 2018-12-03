<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492716MediaFolderTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492716;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder_translation` (
              `media_folder_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255),
              `created_at` DATETIME(3),
              `updated_at` DATETIME(3),
              PRIMARY KEY (`media_folder_id`, `language_id`),
              CONSTRAINT `fk.media_folder_translation.media_folder_id` FOREIGN KEY (`media_folder_id`) 
                REFERENCES `media_folder` (`id`) ON DELETE CASCADE ,
              CONSTRAINT `fk.media_folder_translation.language_id` FOREIGN KEY (`language_id`) 
                REFERENCES `language` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
