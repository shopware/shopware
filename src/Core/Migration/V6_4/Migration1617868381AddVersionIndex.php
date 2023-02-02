<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1617868381AddVersionIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617868381;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `version` ADD INDEX `idx.version.created_at` (`created_at`)');
        $connection->executeStatement('ALTER TABLE `version_commit` ADD INDEX `idx.version_commit.created_at` (`created_at`)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
