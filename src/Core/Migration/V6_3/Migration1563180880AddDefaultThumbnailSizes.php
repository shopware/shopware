<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1563180880AddDefaultThumbnailSizes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563180880;
    }

    public function update(Connection $connection): void
    {
        $thumbnailSizes = $this->upsertThumbnailSizes($connection);

        $foldersWithDefaultThumbnails = ['Product Media', 'Category Media', 'Cms Page Media'];

        foreach ($foldersWithDefaultThumbnails as $folderName) {
            $configurationId = $connection->fetchOne(
                'SELECT media_folder_configuration_id FROM media_folder WHERE name = :name',
                ['name' => $folderName]
            );
            if (!$configurationId) {
                continue;
            }

            foreach ($thumbnailSizes as $thumbnailSize) {
                $connection->executeStatement('
                    REPLACE INTO `media_folder_configuration_media_thumbnail_size` (`media_folder_configuration_id`, `media_thumbnail_size_id`)
                    VALUES (:folderConfigurationId, :thumbnailSizeId)
                ', [
                    'folderConfigurationId' => $configurationId,
                    'thumbnailSizeId' => $thumbnailSize['id'],
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return list<array{id: string, width: int, height: int}>
     */
    private function upsertThumbnailSizes(Connection $connection): array
    {
        $thumbnailSizes = [
            ['width' => 400, 'height' => 400],
            ['width' => 800, 'height' => 800],
            ['width' => 1920, 'height' => 1920],
        ];

        $sizes = [];
        foreach ($thumbnailSizes as $i => $thumbnailSize) {
            /** @var string|false $id */
            $id = $connection->fetchOne(
                'SELECT id FROM media_thumbnail_size WHERE width = :width AND height = :height',
                ['width' => $thumbnailSize['width'], 'height' => $thumbnailSize['height']]
            );
            if ($id) {
                continue;
            }
            $id = Uuid::randomBytes();
            $connection->executeStatement('
                INSERT INTO `media_thumbnail_size` (`id`, `width`, `height`, created_at)
                VALUES (:id, :width, :height, :createdAt)
            ', [
                'id' => $id,
                'width' => $thumbnailSize['width'],
                'height' => $thumbnailSize['height'],
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $thumbnailSize['id'] = $id;
            $sizes[] = $thumbnailSize;
        }

        return $sizes;
    }
}
