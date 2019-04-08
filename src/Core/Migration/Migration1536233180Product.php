<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233180Product extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233180;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `active` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `parent_id` BINARY(16) NULL,
              `parent_version_id` BINARY(16) NULL,
              `tax_id` BINARY(16) NULL,
              `product_manufacturer_id` BINARY(16) NULL,
              `product_manufacturer_version_id` BINARY(16) NULL,
              `product_media_id` BINARY(16) NULL,
              `product_media_version_id` BINARY(16) NULL,
              `unit_id` BINARY(16) NULL,
              `category_tree` JSON NULL,
              `option_ids` JSON NULL,
              `property_ids` JSON NULL,
              `tax` BINARY(16) NULL,
              `manufacturer` BINARY(16) NULL,
              `cover` BINARY(16) NULL,
              `unit` BINARY(16) NULL,
              `media` BINARY(16) NULL,
              `prices` BINARY(16) NULL,
              `services` BINARY(16) NULL,
              `properties` BINARY(16) NULL,
              `categories` BINARY(16) NULL,
              `translations` BINARY(16) NULL,
              `price` JSON NULL,
              `listing_prices` JSON NULL,
              `manufacturer_number` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `ean` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `stock` INT(11) NULL,
              `min_delivery_time` INT(11) NULL,
              `max_delivery_time` INT(11) NULL,
              `restock_time` INT(11) NULL,
              `is_closeout` TINYINT(1) NULL,
              `min_stock` INT(11) unsigned NULL,
              `purchase_steps` INT(11) unsigned NULL,
              `max_purchase` INT(11) unsigned NULL,
              `min_purchase` INT(11) unsigned NULL,
              `purchase_unit` DECIMAL(11,4) unsigned NULL,
              `reference_unit` DECIMAL(10,3) unsigned NULL,
              `shipping_free` TINYINT(4) NULL,
              `purchase_price` DOUBLE NULL,
              `mark_as_topseller` TINYINT(1) unsigned NULL,
              `sales` INT(11) NULL,
              `position` INT(11) unsigned NULL,
              `weight` DECIMAL(10,3) unsigned NULL,
              `width` DECIMAL(10,3) unsigned NULL,
              `height` DECIMAL(10,3) unsigned NULL,
              `length` DECIMAL(10,3) unsigned NULL,
              `allow_notification` TINYINT(1) unsigned NULL,
              `release_date` DATETIME(3) NULL,
              `whitelist_ids` JSON NULL,
              `blacklist_ids` JSON NULL,
              `created_at` DATETIME(3) NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              KEY `idx.auto_increment` (`auto_increment`),
              CONSTRAINT `json.category_tree` CHECK (JSON_VALID(`category_tree`)),
              CONSTRAINT `json.option_ids` CHECK (JSON_VALID(`option_ids`)),
              CONSTRAINT `json.property_ids` CHECK (JSON_VALID(`property_ids`)),
              CONSTRAINT `json.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `json.listing_prices` CHECK (JSON_VALID(`listing_prices`)),
              CONSTRAINT `json.blacklist_ids` CHECK (JSON_VALID(`blacklist_ids`)),
              CONSTRAINT `json.whitelist_ids` CHECK (JSON_VALID(`whitelist_ids`)),
              CONSTRAINT `fk.product.product_manufacturer_id` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`)
                REFERENCES `product_manufacturer` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.product.tax_id` FOREIGN KEY (`tax_id`)
                REFERENCES `tax` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.product.unit_id` FOREIGN KEY (`unit_id`)
                REFERENCES `unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.product.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `product_translation` (
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `additional_text` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `keywords` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `description_long` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `meta_title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `pack_unit` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`product_id`, `product_version_id`, `language_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.product_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_translation.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
