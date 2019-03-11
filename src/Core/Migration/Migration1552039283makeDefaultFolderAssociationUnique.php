<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552039283makeDefaultFolderAssociationUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552039283;
    }

    public function update(Connection $connection): void
    {
        $duplicates = $connection->fetchAll('
          SELECT `default_folder_id` id , MAX(`updated_at`) latest_date, Count(*) c
          FROM `media_folder`
          GROUP BY `default_folder_id`
          HAVING c > 1;
          '
        );

        $updateStmt = $connection->prepare('
            UPDATE `media_folder`
            SET `default_folder_id`=NULL
            WHERE `default_folder_id` = :id AND `updated_at` <> :latest_date
            '
        );

        foreach ($duplicates as $duplicated) {
            $updateStmt->execute($duplicated);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder`
            ADD UNIQUE (`default_folder_id`);
            '
        );
    }
}
