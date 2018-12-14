<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1544778905AddKeepAspectRatioToFolderConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492672;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder_configuration` 
            ADD COLUMN `keep_aspect_ratio` TINYINT(1) DEFAULT \'1\';
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
