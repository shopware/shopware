<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1792743880AddProductDocument extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1792743880;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_document` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `position` INT(11) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`, `version_id`),
                CONSTRAINT `fk.product_document.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_document_translation` (
                `product_document_id` BINARY(16) NOT NULL,
                `product_document_version_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NULL,
                `media_id` BINARY(16) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`product_document_id`, `product_document_version_id`, `language_id`),
                CONSTRAINT `json.product_document_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.product_document_translation.product_document_id` FOREIGN KEY (`product_document_id`, `product_document_version_id`)
                    REFERENCES `product_document` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_document_translation.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_document_translation.media_id` FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        // existing products have no documents yet, writing documents fills the column of the product and its variants
        if (!TableHelper::columnExists($connection, 'product', 'documents')) {
            $this->updateInheritance($connection, 'product', 'documents');
        }

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
    }
}
