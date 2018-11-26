<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237804OrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237804;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_delivery` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `order_id` binary(16) NOT NULL,
              `order_version_id` binary(16) NOT NULL,
              `shipping_order_address_id` binary(16) NOT NULL,
              `shipping_order_address_version_id` binary(16) NOT NULL,
              `shipping_method_id` binary(16) NOT NULL,
              `order_state_id` binary(16) NOT NULL,
              `order_state_version_id` binary(16) NOT NULL,
              `tracking_code` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `shipping_date_earliest` date NOT NULL,
              `shipping_date_latest` date NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `fk.order_delivery.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery.shipping_order_address_id` FOREIGN KEY (`shipping_order_address_id`, `shipping_order_address_version_id`) REFERENCES `order_address` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery.order_state_id` FOREIGN KEY (`order_state_id`, `order_state_version_id`) REFERENCES `order_state` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
