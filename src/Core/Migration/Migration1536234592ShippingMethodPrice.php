<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234592ShippingMethodPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234592;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method_price` (
              `id` binary(16) NOT NULL,
              `shipping_method_id` binary(16) NOT NULL,
              `quantity_from` decimal(10,3) unsigned NOT NULL,
              `price` decimal(10,2) NOT NULL,
              `factor` decimal(10,2) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.shipping_method_quantity_from` (`shipping_method_id`, `quantity_from`),
              CONSTRAINT `fk.shipping_method_price.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
