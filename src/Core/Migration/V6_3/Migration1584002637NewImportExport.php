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
class Migration1584002637NewImportExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584002637;
    }

    public function update(Connection $connection): void
    {
        $this->clearOldImportExportTables($connection);
        $this->addConfigField($connection);
        $this->addSystemDefaultProfiles($connection);
        $this->addInvalidRecordsLog($connection);
        $this->addDefaultImportExportMediaFolder($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * This is OK, because the feature was never released.
     */
    private function clearOldImportExportTables(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM `import_export_log`');
        $connection->executeStatement('DELETE FROM `import_export_file`');
        $connection->executeStatement('DELETE FROM `import_export_profile`');
    }

    private function addConfigField(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE import_export_log
            ADD COLUMN config JSON,
            ADD CONSTRAINT `json.import_export_log.config` CHECK (JSON_VALID(`config`))'
        );
    }

    private function addSystemDefaultProfiles(Connection $connection): void
    {
        foreach ($this->getSystemDefaultProfiles() as $profile) {
            $profile['id'] = Uuid::randomBytes();
            $profile['system_default'] = 1;
            $profile['file_type'] = 'text/csv';
            $profile['delimiter'] = ';';
            $profile['enclosure'] = '"';
            $profile['mapping'] = json_encode($profile['mapping'], \JSON_THROW_ON_ERROR);
            $profile['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $connection->insert('import_export_profile', $profile);
        }
    }

    private function addInvalidRecordsLog(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `import_export_log`
            ADD COLUMN `invalid_records_log_id` BINARY(16),
            ADD CONSTRAINT `fk.import_export_log.invalid_records_log_id`
                FOREIGN KEY (`invalid_records_log_id`)
                REFERENCES `import_export_log` (`id`)
                ON DELETE SET NULL'
        );
    }

    private function addDefaultImportExportMediaFolder(Connection $connection): void
    {
        $defaultFolderId = Uuid::randomBytes();

        $configurationId = Uuid::randomBytes();

        $connection->insert('media_default_folder', [
            'id' => $defaultFolderId,
            'entity' => 'import_export_profile',
            'association_fields' => '[]',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->executeStatement('
                INSERT INTO `media_folder_configuration` (`id`, `thumbnail_quality`, `create_thumbnails`, `private`, created_at)
                VALUES (:id, 80, 1, :private, :createdAt)
            ', [
            'id' => $configurationId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'private' => 0,
        ]);

        foreach ($this->getThumbnailSizes($connection) as $thumbnailSize) {
            $connection->executeStatement('
                    REPLACE INTO `media_folder_configuration_media_thumbnail_size` (`media_folder_configuration_id`, `media_thumbnail_size_id`)
                    VALUES (:folderConfigurationId, :thumbnailSizeId)
                ', [
                'folderConfigurationId' => $configurationId,
                'thumbnailSizeId' => $thumbnailSize['id'],
            ]);
        }

        $connection->insert('media_folder', [
            'id' => Uuid::randomBytes(),
            'default_folder_id' => $defaultFolderId,
            'name' => 'Import Media',
            'media_folder_configuration_id' => $configurationId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return array<int, array{id: string, width: int, height: int}>
     */
    private function getThumbnailSizes(Connection $connection): array
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
            if (!$id) {
                continue;
            }

            $thumbnailSize['id'] = $id;
            $sizes[] = $thumbnailSize;
        }

        return $sizes;
    }

    /**
     * @return list<array{name: string, source_entity: string, mapping: list<array{key: string, mappedKey: string}>}>
     */
    private function getSystemDefaultProfiles(): array
    {
        return [
            [
                'name' => 'Default category',
                'source_entity' => 'category',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'parentId', 'mappedKey' => 'parent_id'],
                    ['key' => 'active', 'mappedKey' => 'active'],

                    ['key' => 'type', 'mappedKey' => 'type'],
                    ['key' => 'visible', 'mappedKey' => 'visible'],
                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.externalLink', 'mappedKey' => 'external_link'],
                    ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],
                    ['key' => 'translations.DEFAULT.metaTitle', 'mappedKey' => 'meta_title'],
                    ['key' => 'translations.DEFAULT.metaDescription', 'mappedKey' => 'meta_description'],

                    ['key' => 'media.id', 'mappedKey' => 'media_id'],
                    ['key' => 'media.url', 'mappedKey' => 'media_url'],
                    ['key' => 'media.mediaFolderId', 'mappedKey' => 'media_folder_id'],
                    ['key' => 'media.mediaType', 'mappedKey' => 'media_type'],
                    ['key' => 'media.translations.DEFAULT.title', 'mappedKey' => 'media_alt'],
                    ['key' => 'media.translations.DEFAULT.alt', 'mappedKey' => 'media_title'],
                ],
            ],
            [
                'name' => 'Default media',
                'source_entity' => 'media',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'mediaFolderId', 'mappedKey' => 'folder_id'],
                    ['key' => 'url', 'mappedKey' => 'url'],

                    ['key' => 'private', 'mappedKey' => 'private'],

                    ['key' => 'mediaType', 'mappedKey' => 'type'],
                    ['key' => 'translations.DEFAULT.title', 'mappedKey' => 'alt'],
                    ['key' => 'translations.DEFAULT.alt', 'mappedKey' => 'title'],
                ],
            ],
            [
                'name' => 'Default product',
                'source_entity' => 'product',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],

                    ['key' => 'productNumber', 'mappedKey' => 'product_number'],
                    ['key' => 'active', 'mappedKey' => 'active'],
                    ['key' => 'stock', 'mappedKey' => 'stock'],
                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],

                    ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net'],
                    ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross'],

                    ['key' => 'tax.id', 'mappedKey' => 'tax_id'],
                    ['key' => 'tax.taxRate', 'mappedKey' => 'tax_rate'],
                    ['key' => 'tax.name', 'mappedKey' => 'tax_name'],

                    ['key' => 'cover.media.id', 'mappedKey' => 'cover_media_id'],
                    ['key' => 'cover.media.url', 'mappedKey' => 'cover_media_url'],
                    ['key' => 'cover.media.translations.DEFAULT.title', 'mappedKey' => 'cover_media_title'],
                    ['key' => 'cover.media.translations.DEFAULT.alt', 'mappedKey' => 'cover_media_alt'],

                    ['key' => 'manufacturer.id', 'mappedKey' => 'manufacturer_id'],
                    ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name'],

                    ['key' => 'categories', 'mappedKey' => 'categories'],
                    ['key' => 'visibilities.all', 'mappedKey' => 'sales_channel'],
                ],
            ],
        ];
    }
}
