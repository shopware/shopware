<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1555313295CreateDefaultFoldersForBasicEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555313295;
    }

    public function update(Connection $connection): void
    {
        $notCreatedDefaultFolders = $connection->executeQuery('
            SELECT `media_default_folder`.`id` default_folder_id, `media_default_folder`.`entity` entity
            FROM `media_default_folder`
                LEFT JOIN `media_folder` ON `media_folder`.`default_folder_id` = `media_default_folder`.`id`
            WHERE `media_folder`.`id` IS NULL
        ')->fetchAll();

        foreach ($notCreatedDefaultFolders as $notCreatedDefaultFolder) {
            $this->createDefaultFolder(
                $connection,
                $notCreatedDefaultFolder['default_folder_id'],
                $notCreatedDefaultFolder['entity']
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }

    private function createDefaultFolder(Connection $connection, string $defaultFolderId, string $entity): void
    {
        $connection->transactional(function (Connection $connection) use ($defaultFolderId, $entity) {
            $configurationId = Uuid::randomBytes();
            $folderId = Uuid::randomBytes();
            $folderName = $this->getMediaFolderName($entity);

            $stmt = $connection->executeUpdate('
                INSERT INTO `media_folder_configuration` (`id`, `thumbnail_quality`, `create_thumbnails`, created_at)
                VALUES (:id, 80, 1, NOW())
            ', [
                'id' => $configurationId,
            ]);

            $connection->executeUpdate('
                INSERT into `media_folder` (`id`, `name`, `default_folder_id`, `media_folder_configuration_id`, `use_parent_configuration`, `child_count`, `created_at`)
                VALUES (:folderId, :folderName, :defaultFolderId, :configurationId, 0, 0, NOW())
            ', [
                'folderId' => $folderId,
                'folderName' => $folderName,
                'defaultFolderId' => $defaultFolderId,
                'configurationId' => $configurationId,
            ]);

            $connection->executeUpdate('
                UPDATE `media_default_folder` SET updated_at = NOW()
                WHERE `media_default_folder`.id = :defaultFolderId  
            ', [
                'defaultFolderId' => $defaultFolderId,
            ]);
        });
    }

    private function getMediaFolderName(string $entity): string
    {
        $capitalizedEntityParts = array_map(function ($part) {
            return ucfirst($part);
        },
            explode('_', $entity)
        );

        return implode(' ', $capitalizedEntityParts) . ' Media';
    }
}
