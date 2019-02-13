<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232680ShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232680;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method` (
              `id` BINARY(16) NOT NULL,
              `type` INT(11) unsigned NOT NULL,
              `active` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `position` INT(11) NOT NULL DEFAULT 1,
              `calculation` INT(1) unsigned NOT NULL DEFAULT 0,
              `surcharge_calculation` INT(1) unsigned NULL,
              `tax_calculation` INT(11) unsigned NOT NULL DEFAULT 0,
              `min_delivery_time` INT(11) NULL DEFAULT 1,
              `max_delivery_time` INT(11) NULL DEFAULT 2,
              `shipping_free` DECIMAL(10,2) unsigned NULL,
              `bind_shippingfree` TINYINT(1) NOT NULL,
              `bind_time_from` INT(11) unsigned NULL,
              `bind_time_to` INT(11) unsigned NULL,
              `bind_instock` TINYINT(1) NULL,
              `bind_laststock` TINYINT(1) NULL,
              `bind_weekday_from` INT(1) unsigned NULL,
              `bind_weekday_to` INT(1) unsigned NULL,
              `bind_weight_from` DECIMAL(10,3) NULL,
              `bind_weight_to` DECIMAL(10,3) NULL,
              `bind_price_from` DECIMAL(10,2) NULL,
              `bind_price_to` DECIMAL(10,2) NULL,
              `bind_sql` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
              `status_link` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
              `calculation_sql` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `shipping_method_translation` (
              `shipping_method_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `comment` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`shipping_method_id`, `language_id`),
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.shipping_method_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_translation.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
