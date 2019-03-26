<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551962861ShippingMethodPriceRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551962861;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method_price_rule` (
              `id` BINARY(16) NOT NULL,
              `shipping_method_id` BINARY(16) NOT NULL,
              `calculation` INT(1) unsigned NOT NULL DEFAULT 1,
              `rule_id` BINARY(16) NOT NULL,
              `currency_id` BINARY(16) NOT NULL,
              `price` DECIMAL(10,2) NOT NULL,
              `quantity_start` INT(11) NOT NULL,
              `quantity_end` INT(11) NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.shipping_method_quantity_start` UNIQUE KEY (`shipping_method_id`, `rule_id`, `currency_id`, `quantity_start`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.shipping_method_price_rule.shipping_method_id` FOREIGN KEY (`shipping_method_id`) 
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_price_rule.currency_id` FOREIGN KEY (`currency_id`) 
                REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_price_rule.rule_id` FOREIGN KEY (`rule_id`) 
                REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `shipping_method` 
            DROP COLUMN `calculation`;
        ');

        $connection->exec('
            DROP TABLE shipping_method_price;
        ');
    }
}
