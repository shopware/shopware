<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1544523692AddThumbnailQuality extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1544523692;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media_folder_configuration`
            ADD COLUMN `thumbnail_quality` int(11) DEFAULT 80 AFTER `create_thumbnails`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
