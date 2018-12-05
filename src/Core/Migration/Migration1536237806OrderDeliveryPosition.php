<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237806OrderDeliveryPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237806;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_delivery_position` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `order_delivery_id` binary(16) NOT NULL,
              `order_delivery_version_id` binary(16) NOT NULL,
              `order_line_item_id` binary(16) NOT NULL,
              `order_line_item_version_id` binary(16) NOT NULL,
              `unit_price` double NOT NULL,
              `total_price` double NOT NULL,
              `quantity` double NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.order_delivery_position.order_delivery_id` FOREIGN KEY (`order_delivery_id`, `order_delivery_version_id`) REFERENCES `order_delivery` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery_position.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`) REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
