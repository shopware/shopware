<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233140Category extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233140;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `category` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `parent_id` BINARY(16) NULL,
              `parent_version_id` BINARY(16) NULL,
              `media_id` BINARY(16) NULL,
              `path` LONGTEXT COLLATE utf8mb4_unicode_ci,
              `position` INT(11) unsigned NOT NULL DEFAULT 1,
              `level` INT(11) unsigned NOT NULL DEFAULT 1,
              `template` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `is_blog` TINYINT(1) NOT NULL DEFAULT 0,
              `external` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `hide_filter` TINYINT(1) NOT NULL DEFAULT 0,
              `hide_top` TINYINT(1) NOT NULL DEFAULT 0,
              `product_box_layout` VARCHAR(50) COLLATE utf8mb4_unicode_ci NULL,
              `hide_sortings` TINYINT(1) NOT NULL DEFAULT 0,
              `sorting_ids` LONGTEXT COLLATE utf8mb4_unicode_ci,
              `facet_ids` LONGTEXT COLLATE utf8mb4_unicode_ci,
              `child_count` INT(11) unsigned NOT NULL DEFAULT 0,
              `display_nested_products` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              KEY `idx.position` (`position`),
              KEY `idx.level` (`level`),
              KEY `idx.auto_increment` (`auto_increment`),
              CONSTRAINT `json.sorting_ids` CHECK (JSON_VALID(`sorting_ids`)),
              CONSTRAINT `json.facet_ids` CHECK (JSON_VALID(`facet_ids`)),
              CONSTRAINT `fk.category.media_id` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `fk.category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `category_translation` (
              `category_id` BINARY(16) NOT NULL,
              `category_version_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `meta_keywords` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `meta_title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `meta_description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `cms_headline` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `cms_description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`category_id`, `category_version_id`, `language_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.category_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.category_translation.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
