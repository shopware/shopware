<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553695835RemoveThumbnailSizesFromDefaultFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553695835;
    }

    public function update(Connection $connection): void
    {
        // no update necessary
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `media_default_folder` DROP COLUMN `thumbnail_sizes`');
    }
}
