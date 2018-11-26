<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239250Product extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239250;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `parent_id` binary(16) DEFAULT NULL,
              `parent_version_id` binary(16) DEFAULT NULL,
              `tax_id` binary(16) DEFAULT NULL,
              `product_manufacturer_id` binary(16) DEFAULT NULL,
              `product_manufacturer_version_id` binary(16) DEFAULT NULL,
              `product_media_id` binary(16) DEFAULT NULL,
              `product_media_version_id` binary(16) DEFAULT NULL,
              `unit_id` binary(16) DEFAULT NULL,
              `category_tree` JSON NULL,
              `variation_ids` JSON NULL,
              `datasheet_ids` JSON NULL,
              `tax` binary(16) NULL,
              `manufacturer` binary(16) NULL,
              `cover` binary(16) NULL,
              `unit` binary(16) NULL,
              `media` binary(16) NULL,
              `priceRules` binary(16) NULL,
              `services` binary(16) NULL,
              `datasheet` binary(16) NULL,
              `categories` binary(16) NULL,
              `translations` binary(16) NULL,
              `price` JSON DEFAULT NULL,
              `listing_prices` JSON DEFAULT NULL,
              `manufacturer_number` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
              `ean` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `stock` int(11) DEFAULT NULL,
              `min_delivery_time` int(11) DEFAULT NULL,
              `max_delivery_time` int(11) DEFAULT NULL,
              `restock_time` int(11) DEFAULT NULL,
              `is_closeout` tinyint(1) DEFAULT NULL,
              `min_stock` int(11) unsigned DEFAULT NULL,
              `purchase_steps` int(11) unsigned DEFAULT NULL,
              `max_purchase` int(11) unsigned DEFAULT NULL,
              `min_purchase` int(11) unsigned DEFAULT NULL,
              `purchase_unit` decimal(11,4) unsigned DEFAULT NULL,
              `reference_unit` decimal(10,3) unsigned DEFAULT NULL,
              `shipping_free` tinyint(4) DEFAULT NULL,
              `purchase_price` double DEFAULT NULL,
              `mark_as_topseller` tinyint(1) unsigned DEFAULT NULL,
              `sales` int(11) DEFAULT NULL,
              `position` int(11) unsigned DEFAULT NULL,
              `weight` decimal(10,3) unsigned DEFAULT NULL,
              `width` decimal(10,3) unsigned DEFAULT NULL,
              `height` decimal(10,3) unsigned DEFAULT NULL,
              `length` decimal(10,3) unsigned DEFAULT NULL,
              `allow_notification` tinyint(1) unsigned DEFAULT NULL,
              `release_date` datetime(3) DEFAULT NULL,
              `whitelist_ids` JSON NULL,
              `blacklist_ids` JSON NULL,
              `created_at` datetime(3) DEFAULT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              KEY `idx.auto_increment` (`auto_increment`),
              CONSTRAINT `json.category_tree` CHECK (JSON_VALID(`category_tree`)),
              CONSTRAINT `json.variation_ids` CHECK (JSON_VALID(`variation_ids`)),
              CONSTRAINT `json.datasheet_ids` CHECK (JSON_VALID(`datasheet_ids`)),
              CONSTRAINT `json.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `json.listing_prices` CHECK (JSON_VALID(`listing_prices`)),
              CONSTRAINT `json.blacklist_ids` CHECK (JSON_VALID(`blacklist_ids`)),
              CONSTRAINT `json.whitelist_ids` CHECK (JSON_VALID(`whitelist_ids`)),
              CONSTRAINT `fk.product.product_manufacturer_id` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`) REFERENCES `product_manufacturer` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.product.tax_id` FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.product.unit_id` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.product.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product.catalog_id` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
