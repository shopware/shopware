<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @no-indexer-required: a new top-level folder without children or thumbnail sizes leaves the folder indexers nothing to compute
 */
#[Package('inventory')]
class Migration1792998292AddProductDocumentMediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792998292;
    }

    public function update(Connection $connection): void
    {
        $this->addDefaultMediaFolder($connection);
    }

    private function addDefaultMediaFolder(Connection $connection): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $defaultFolderId = $connection->fetchOne(
            'SELECT `id` FROM `media_default_folder` WHERE `entity` = :entity',
            ['entity' => ProductDocumentDefinition::ENTITY_NAME]
        );

        if ($defaultFolderId === false) {
            $defaultFolderId = Uuid::randomBytes();

            $connection->insert('media_default_folder', [
                'id' => $defaultFolderId,
                'entity' => ProductDocumentDefinition::ENTITY_NAME,
                'created_at' => $createdAt,
            ]);
        }

        $folderExists = $connection->fetchOne(
            'SELECT 1 FROM `media_folder` WHERE `default_folder_id` = :defaultFolderId',
            ['defaultFolderId' => $defaultFolderId]
        );

        if ($folderExists !== false) {
            return;
        }

        $connection->transactional(static function (Connection $connection) use ($defaultFolderId, $createdAt): void {
            $configurationId = Uuid::randomBytes();

            $connection->insert('media_folder_configuration', [
                'id' => $configurationId,
                'create_thumbnails' => 0,
                'thumbnail_quality' => 80,
                'private' => 0,
                'created_at' => $createdAt,
            ]);

            $connection->insert('media_folder', [
                'id' => Uuid::randomBytes(),
                'default_folder_id' => $defaultFolderId,
                'name' => 'Product documents',
                'media_folder_configuration_id' => $configurationId,
                'use_parent_configuration' => 0,
                'created_at' => $createdAt,
            ]);
        });
    }
}
