<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1780325388AddMediaFolderCreatedAtIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780325388;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'media', 'idx.media.media_folder_id_created_at_id')) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `idx.media.media_folder_id_created_at_id` ON `media` (`media_folder_id`, `created_at`, `id`)'
        );
    }
}
