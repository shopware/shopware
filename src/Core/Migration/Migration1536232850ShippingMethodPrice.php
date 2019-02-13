<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232850ShippingMethodPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232850;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method_price` (
              `id` BINARY(16) NOT NULL,
              `shipping_method_id` BINARY(16) NOT NULL,
              `quantity_from` DECIMAL(10, 3) unsigned NOT NULL,
              `price` DECIMAL(10, 2) NOT NULL,
              `factor` DECIMAL(10, 2) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.shipping_method_quantity_from` UNIQUE KEY (`shipping_method_id`, `quantity_from`),
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.shipping_method_price.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
