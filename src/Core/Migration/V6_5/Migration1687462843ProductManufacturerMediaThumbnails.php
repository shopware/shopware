<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\EnsureThumbnailSizesTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1687462843ProductManufacturerMediaThumbnails extends MigrationStep
{
    use EnsureThumbnailSizesTrait;

    public function getCreationTimestamp(): int
    {
        return 1687462843;
    }

    public function update(Connection $connection): void
    {
        $thumbnailSizes = [
            ['width' => 200, 'height' => 200],
            ['width' => 360, 'height' => 360],
            ['width' => 1920, 'height' => 1920],
        ];

        $thumbnailSizeIds = $this->ensureThumbnailSizes($thumbnailSizes, $connection);

        $configurationId = $connection->fetchOne(
            'SELECT media_folder_configuration_id FROM media_folder WHERE name = :name',
            ['name' => 'Product Manufacturer Media']
        );

        if (!$configurationId) {
            return;
        }

        $statement = $connection->prepare('
                    REPLACE INTO `media_folder_configuration_media_thumbnail_size` (`media_folder_configuration_id`, `media_thumbnail_size_id`)
                    VALUES (:folderConfigurationId, :thumbnailSizeId)
                ');

        $statement->bindValue('folderConfigurationId', $configurationId);
        foreach ($thumbnailSizeIds as $thumbnailSizeId) {
            $statement->bindValue('thumbnailSizeId', $thumbnailSizeId);
            $statement->executeStatement();
        }

        $this->registerIndexer($connection, 'media_folder_configuration.indexer');
    }
}
