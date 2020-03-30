<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1584002637NewImportExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584002637;
    }

    public function update(Connection $connection): void
    {
        // abort if old feature is enabled?

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
        $connection->executeUpdate('DELETE FROM `import_export_log`');
        $connection->executeUpdate('DELETE FROM `import_export_file`');
        $connection->executeUpdate('DELETE FROM `import_export_profile`');
    }

    private function addConfigField(Connection $connection): void
    {
        $connection->executeUpdate(
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
            $profile['mapping'] = json_encode($profile['mapping']);
            $profile['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $connection->insert('import_export_profile', $profile);
        }
    }

    private function addInvalidRecordsLog(Connection $connection): void
    {
        $connection->executeUpdate(
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

        $connection->executeUpdate('
                INSERT INTO `media_folder_configuration` (`id`, `thumbnail_quality`, `create_thumbnails`, `private`, created_at)
                VALUES (:id, 80, 1, :private, :createdAt)
            ', [
            'id' => $configurationId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'private' => 0,
        ]);

        foreach ($this->getThumbnailSizes($connection) as $thumbnailSize) {
            $connection->executeUpdate('
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

    private function getThumbnailSizes(Connection $connection): array
    {
        $thumbnailSizes = [
            ['width' => 400, 'height' => 400],
            ['width' => 800, 'height' => 800],
            ['width' => 1920, 'height' => 1920],
        ];

        $stmt = $connection->prepare('SELECT id FROM media_thumbnail_size WHERE width = :width AND height = :height');
        foreach ($thumbnailSizes as $i => $thumbnailSize) {
            $stmt->execute(['width' => $thumbnailSize['width'], 'height' => $thumbnailSize['height']]);
            $id = $stmt->fetchColumn();
            if (!$id) {
                unset($thumbnailSizes[$i]);

                continue;
            }

            $thumbnailSizes[$i]['id'] = $id;
        }

        return $thumbnailSizes;
    }

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

                    // ['key' => 'path', 'mappedKey' => 'path'],
                    ['key' => 'type', 'mappedKey' => 'type'],
                    ['key' => 'visible', 'mappedKey' => 'visible'],
                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.slotConfig', 'mappedKey' => 'slot_config'],
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
                    ['key' => 'media.translations.DEFAULT.customFields', 'mappedKey' => 'media_custom_fields'],
                ],
            ],
            [
                'name' => 'Default product',
                'source_entity' => 'product',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'parentId', 'mappedKey' => 'parent_id'],
                    ['key' => 'productNumber', 'mappedKey' => 'product_number'],

                    ['key' => 'stock', 'mappedKey' => 'stock'],
                    ['key' => 'ean', 'mappedKey' => 'ean'],
                    ['key' => 'active', 'mappedKey' => 'active'],
                    ['key' => 'minPurchase', 'mappedKey' => 'min_purchase'],
                    ['key' => 'maxPurchase', 'mappedKey' => 'max_purchase'],
                    ['key' => 'purchaseSteps', 'mappedKey' => 'purchase_steps'],

                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],

                    ['key' => 'tax.id', 'mappedKey' => 'tax_id'],
                    ['key' => 'tax.taxRate', 'mappedKey' => 'tax_rate'],
                    ['key' => 'tax.name', 'mappedKey' => 'tax_name'],

                    ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net'],
                    ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross'],
                    ['key' => 'price.EUR.net', 'mappedKey' => 'price_net_eur'],
                    ['key' => 'price.EUR.gross', 'mappedKey' => 'price_gross_eur'],
                    ['key' => 'price.USD.net', 'mappedKey' => 'price_net_usd'],
                    ['key' => 'price.USD.gross', 'mappedKey' => 'price_gross_usd'],

                    ['key' => 'visibilities.all', 'mappedKey' => 'visibilities_all'],
                    ['key' => 'visibilities.link', 'mappedKey' => 'visibilities_link'],
                    ['key' => 'visibilities.search', 'mappedKey' => 'visibilities_search'],

                    ['key' => 'categories', 'mappedKey' => 'categories'],
                    ['key' => 'customFields', 'mappedKey' => 'custom_fields'],

                    ['key' => 'cover.media.id', 'mappedKey' => 'cover_media_id'],
                    ['key' => 'cover.media.url', 'mappedKey' => 'cover_media_url'],
                    ['key' => 'cover.media.mediaFolderId', 'mappedKey' => 'cover_media_folder_id'],
                    ['key' => 'cover.media.mediaType', 'mappedKey' => 'cover_media_type'],
                    ['key' => 'cover.media.translations.DEFAULT.title', 'mappedKey' => 'cover_media_alt'],
                    ['key' => 'cover.media.translations.DEFAULT.alt', 'mappedKey' => 'cover_media_title'],
                    ['key' => 'cover.media.translations.DEFAULT.customFields', 'mappedKey' => 'cover_media_custom_fields'],

                    ['key' => 'manufacturer.id', 'mappedKey' => 'manufacturer_id'],
                    ['key' => 'manufacturer.link', 'mappedKey' => 'manufacturer_link'],
                    ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name'],
                    ['key' => 'manufacturer.translations.DEFAULT.description', 'mappedKey' => 'manufacturer_description'],
                    ['key' => 'manufacturer.translations.DEFAULT.customFields', 'mappedKey' => 'manufacturer_custom_fields'],
                    ['key' => 'manufacturer.media.id', 'mappedKey' => 'manufacturer_media_id'],
                    ['key' => 'manufacturer.media.url', 'mappedKey' => 'manufacturer_media_url'],
                    ['key' => 'manufacturer.media.mediaFolderId', 'mappedKey' => 'manufacturer_media_folder_id'],
                    ['key' => 'manufacturer.media.mediaType', 'mappedKey' => 'manufacturer_media_type'],
                    ['key' => 'manufacturer.media.translations.DEFAULT.title', 'mappedKey' => 'manufacturer_media_alt'],
                    ['key' => 'manufacturer.media.translations.DEFAULT.alt', 'mappedKey' => 'manufacturer_media_title'],
                    ['key' => 'manufacturer.media.translations.DEFAULT.customFields', 'mappedKey' => 'manufacturer_media_custom_fields'],

                    ['key' => 'options', 'mappedKey' => 'options'],
                    ['key' => 'properties', 'mappedKey' => 'properties'],
                ],
            ],
            [
                'name' => 'Default properties',
                'source_entity' => 'property_group_option',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'colorHexCode', 'mappedKey' => 'color_hex_code'],
                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.position', 'mappedKey' => 'position'],
                    ['key' => 'translations.DEFAULT.customFields', 'mappedKey' => 'customFields'],

                    ['key' => 'group.id', 'mappedKey' => 'group_id'],
                    ['key' => 'group.displayType', 'mappedKey' => 'group_display_type'],
                    ['key' => 'group.sortingType', 'mappedKey' => 'group_sorting_type'],
                    ['key' => 'group.translations.DEFAULT.name', 'mappedKey' => 'group_name'],
                    ['key' => 'group.translations.DEFAULT.description', 'mappedKey' => 'group_description'],
                    ['key' => 'group.translations.DEFAULT.position', 'mappedKey' => 'group_position'],
                    ['key' => 'group.translations.DEFAULT.customFields', 'mappedKey' => 'group_customFields'],

                    ['key' => 'media.id', 'mappedKey' => 'media_id'],
                    ['key' => 'media.url', 'mappedKey' => 'media_url'],
                    ['key' => 'media.mediaFolderId', 'mappedKey' => 'media_folder_id'],
                    ['key' => 'media.mediaType', 'mappedKey' => 'media_type'],
                    ['key' => 'media.translations.DEFAULT.title', 'mappedKey' => 'media_alt'],
                    ['key' => 'media.translations.DEFAULT.alt', 'mappedKey' => 'media_title'],
                    ['key' => 'media.translations.DEFAULT.customFields', 'mappedKey' => 'media_custom_fields'],
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
                    ['key' => 'translations.DEFAULT.customFields', 'mappedKey' => 'custom_fields'],
                ],
            ],
            [
                'name' => 'Default newsletter recipients',
                'source_entity' => 'newsletter_recipient',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'email', 'mappedKey' => 'email'],
                    ['key' => 'title', 'mappedKey' => 'title'],
                    ['key' => 'firstName', 'mappedKey' => 'first_name'],
                    ['key' => 'lastName', 'mappedKey' => 'last_name'],
                    ['key' => 'zipCode', 'mappedKey' => 'zip_code'],
                    ['key' => 'city', 'mappedKey' => 'city'],
                    ['key' => 'street', 'mappedKey' => 'street'],
                    ['key' => 'status', 'mappedKey' => 'status'],
                    ['key' => 'hash', 'mappedKey' => 'hash'],
                    ['key' => 'salesChannelId', 'mappedKey' => 'sales_channel_id'],
                    ['key' => 'customFields', 'mappedKey' => 'custom_fields'],
                    ['key' => 'confirmedAt', 'mappedKey' => 'confirmed_at'],
                    ['key' => 'salutation.salutationKey', 'mappedKey' => 'salutation_key'],
                ],
            ],
            [
                'name' => 'Default customer',
                'source_entity' => 'customer',
                'mapping' => [
                    ['key' => 'firstName', 'mappedKey' => 'first_name'],
                    ['key' => 'lastName', 'mappedKey' => 'last_name'],
                    ['key' => 'email', 'mappedKey' => 'email'],
                    ['key' => 'customerNumber', 'mappedKey' => 'customer_number'],
                    ['key' => 'salesChannelId', 'mappedKey' => 'sales_channel'],
                    ['key' => 'birthday', 'mappedKey' => 'birthday'],
                    ['key' => 'salutationId', 'mappedKey' => 'salutation'],
                    ['key' => 'defaultPaymentMethodId', 'mappedKey' => 'default_payment_method'],
                    ['key' => 'groupId', 'mappedKey' => 'customer_group'],
                    ['key' => 'active', 'mappedKey' => 'active'],

                    ['key' => 'defaultBillingAddress.firstName', 'mappedKey' => 'billing_first_name'],
                    ['key' => 'defaultBillingAddress.lastName', 'mappedKey' => 'billing_last_name'],
                    ['key' => 'defaultBillingAddress.salutationId', 'mappedKey' => 'billing_salutation'],
                    ['key' => 'defaultBillingAddress.street', 'mappedKey' => 'billing_street'],
                    ['key' => 'defaultBillingAddress.zipcode', 'mappedKey' => 'billing_zip_code'],
                    ['key' => 'defaultBillingAddress.city', 'mappedKey' => 'billing_city'],
                    ['key' => 'defaultBillingAddress.countryId', 'mappedKey' => 'billing_country'],
                ],
            ],
            [
                'name' => 'Simple product',
                'source_entity' => 'product',
                'mapping' => [
                    ['key' => 'id', 'mappedKey' => 'id'],
                    ['key' => 'parentId', 'mappedKey' => 'parent_id'],

                    ['key' => 'productNumber', 'mappedKey' => 'product_number'],
                    ['key' => 'active', 'mappedKey' => 'active'],
                    ['key' => 'stock', 'mappedKey' => 'stock'],
                    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                    ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],

                    ['key' => 'price.EUR.net', 'mappedKey' => 'price_net_eur'],
                    ['key' => 'price.EUR.gross', 'mappedKey' => 'price_gross_eur'],

                    ['key' => 'tax.id', 'mappedKey' => 'tax_id'],
                    ['key' => 'tax.taxRate', 'mappedKey' => 'tax_rate'],
                    ['key' => 'tax.name', 'mappedKey' => 'tax_name'],

                    ['key' => 'cover.media.id', 'mappedKey' => 'cover_media_id'],
                    ['key' => 'cover.media.url', 'mappedKey' => 'cover_media_url'],
                    ['key' => 'cover.media.translations.DEFAULT.title', 'mappedKey' => 'cover_media_alt'],

                    ['key' => 'manufacturer.id', 'mappedKey' => 'manufacturer_id'],
                    ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name'],

                    ['key' => 'categories', 'mappedKey' => 'categories'],
                    ['key' => 'visibilities.all', 'mappedKey' => 'sales_channel'],
                ],
            ],
        ];
    }
}
