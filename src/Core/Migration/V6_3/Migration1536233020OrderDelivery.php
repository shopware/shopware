<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233020OrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233020;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `order_delivery` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `state_id` BINARY(16) NOT NULL,
              `shipping_order_address_id` BINARY(16) NULL,
              `shipping_order_address_version_id` BINARY(16) NULL,
              `shipping_method_id` BINARY(16) NOT NULL,
              `tracking_code` VARCHAR(200) COLLATE utf8mb4_unicode_ci NULL,
              `shipping_date_earliest` DATE NOT NULL,
              `shipping_date_latest` DATE NOT NULL,
              `shipping_costs` JSON NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              INDEX `idx.state_index` (`state_id`),
              CONSTRAINT `json.order_delivery.shipping_costs` CHECK (JSON_VALID(`shipping_costs`)),
              CONSTRAINT `json.order_delivery.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_delivery.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery.shipping_order_address_id` FOREIGN KEY (`shipping_order_address_id`, `shipping_order_address_version_id`)
                REFERENCES `order_address` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
