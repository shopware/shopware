<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492733AddMediaFolderToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492733;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media`
             ADD COLUMN `media_folder_id` BINARY(16),
             ADD CONSTRAINT `fk_media.media_folder_id`
               FOREIGN KEY (`media_folder_id`) REFERENCES `media_folder` (`id`) ON DELETE SET NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
