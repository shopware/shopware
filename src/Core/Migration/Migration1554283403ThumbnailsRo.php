<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554283403ThumbnailsRo extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554283403;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `media` ADD COLUMN `thumbnails_ro` LONGBLOB NULL AFTER `media_type`;
SQL;

        $connection->exec($sql);
        $sql = <<<SQL
ALTER TABLE `media_folder_configuration` ADD COLUMN `media_thumbnail_sizes_ro` LONGBLOB NULL AFTER `thumbnail_quality`;
SQL;

        $connection->exec($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
