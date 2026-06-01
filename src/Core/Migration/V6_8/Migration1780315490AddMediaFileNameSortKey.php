<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1780315490AddMediaFileNameSortKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780315490;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'media', 'file_name_sort_key')) {
            $connection->executeStatement(
                <<<'SQL'
                ALTER TABLE `media`
                    ADD COLUMN `file_name_sort_key` VARCHAR(255)
                    GENERATED ALWAYS AS (LEFT(`file_name`, 255)) STORED
                    AFTER `file_name`;
                SQL
            );
        }

        if (!TableHelper::indexExists($connection, 'media', 'fk.media.media_folder_id')) {
            $connection->executeStatement(
                <<<'SQL'
                CREATE INDEX `fk.media.media_folder_id`
                    ON `media` (`media_folder_id`);
                SQL
            );
        }

        if (TableHelper::indexExists($connection, 'media', 'idx.media.media_folder_id_file_name_sort_key_id')) {
            return;
        }

        $connection->executeStatement(
            <<<'SQL'
            CREATE INDEX `idx.media.media_folder_id_file_name_sort_key_id`
                ON `media` (`media_folder_id`, `file_name_sort_key`, `id`);
            SQL
        );
    }
}
