<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492672MediaFolderConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492672;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder_configuration` (
              `id` BINARY(16),
              `create_thumbnails` TINYINT(1),
              `created_at` DATETIME(3),
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`)
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
