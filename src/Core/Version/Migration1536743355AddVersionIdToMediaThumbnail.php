<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536743355AddVersionIdToMediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536743355;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media_thumbnail`
              ADD COLUMN `version_id` binary(16) NOT NULL
        ');

        $connection->executeQuery('
            ALTER TABLE `media_thumbnail` DROP PRIMARY KEY 
        ');

        $connection->executeQuery('
            ALTER TABLE `media_thumbnail` ADD PRIMARY KEY(`id`, `tenant_id`, `version_id`) 
        ');

        $connection->executeQuery('
            UPDATE `media_thumbnail`
               SET `version_id` = `media_version_id`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
