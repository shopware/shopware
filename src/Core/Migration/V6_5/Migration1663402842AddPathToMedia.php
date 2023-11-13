<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1663402842AddPathToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663402842;
    }

    public function update(Connection $connection): void
    {
        $this->updateMedia($connection);

        $this->updateThumbnail($connection);

        $this->registerIndexer($connection, 'media.path.post_update');
    }

    public function updateMedia(Connection $connection): void
    {
        if ($this->columnExists($connection, 'media', 'path')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `media` ADD COLUMN `path` VARCHAR(2048) NULL');
    }

    public function updateThumbnail(Connection $connection): void
    {
        if ($this->columnExists($connection, 'media_thumbnail', 'path')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `media_thumbnail` ADD COLUMN `path` VARCHAR(2048) NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
