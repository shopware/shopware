<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542963312AddMediaFolderToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542963312;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media`
             ADD COLUMN `media_folder_id` BINARY(16),
             ADD COLUMN `media_folder_version_id` BINARY(16),
             ADD CONSTRAINT `fk_media.media_folder_id`
               FOREIGN KEY (`media_folder_id`, `media_folder_version_id`)
               REFERENCES `media_folder` (`id`, `version_id`) ON DELETE SET NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
