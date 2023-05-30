<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232880Category extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232880;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `category` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `parent_id` BINARY(16) NULL,
              `parent_version_id` BINARY(16) NULL,
              `media_id` BINARY(16) NULL,
              `path` LONGTEXT COLLATE utf8mb4_unicode_ci,
              `after_category_id` BINARY(16),
              `after_category_version_id` BINARY(16),
              `level` INT(11) unsigned NOT NULL DEFAULT 1,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `child_count` INT(11) unsigned NOT NULL DEFAULT 0,
              `display_nested_products` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `visible` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `type` VARCHAR(32) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              KEY `idx.level` (`level`),
              KEY `idx.auto_increment` (`auto_increment`),
              CONSTRAINT `fk.category.media_id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `fk.category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `category_translation` (
              `category_id` BINARY(16) NOT NULL,
              `category_version_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `external_link` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `description` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`category_id`, `category_version_id`, `language_id`),
              CONSTRAINT `json.category_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.category_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.category_translation.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
