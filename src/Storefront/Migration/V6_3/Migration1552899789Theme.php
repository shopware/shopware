<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1552899789Theme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552899789;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `theme` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NULL,
              `name` VARCHAR(255) NOT NULL,
              `author` VARCHAR(255) NOT NULL,
              `preview_media_id` BINARY(16) NULL,
              `parent_theme_id` BINARY(16) NULL,
              `base_config` JSON NULL,
              `config_values` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.theme.technical_name` UNIQUE (`technical_name`),
              CONSTRAINT `json.theme.base_config` CHECK (JSON_VALID(`base_config`)),
              CONSTRAINT `json.theme.config_values` CHECK (JSON_VALID(`config_values`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `theme_translation` (
              `theme_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `labels` JSON NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`theme_id`, `language_id`),
              CONSTRAINT `json.theme_translation.labels` CHECK (JSON_VALID(`labels`)),
              CONSTRAINT `json.theme_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.theme_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.theme_translation.theme_id` FOREIGN KEY (`theme_id`)
                REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `theme_sales_channel` (
              `theme_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`sales_channel_id`),
              CONSTRAINT `fk.theme_sales_channel.theme_id` FOREIGN KEY (`theme_id`)
                REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.theme_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $defaultFolderId = Uuid::randomBytes();
        $connection->insert('media_default_folder', [
            'id' => $defaultFolderId,
            'association_fields' => '["themes"]',
            'entity' => 'theme',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $mediaFolderConfigurationId = Uuid::randomBytes();
        $connection->insert('media_folder_configuration', [
            'id' => $mediaFolderConfigurationId,
            'no_association' => true,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('media_folder', [
            'id' => Uuid::randomBytes(),
            'default_folder_id' => $defaultFolderId,
            'media_folder_configuration_id' => $mediaFolderConfigurationId,
            'name' => 'Theme Media',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
