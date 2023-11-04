<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233040OrderDeliveryPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233040;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `order_delivery_position` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_delivery_id` BINARY(16) NOT NULL,
              `order_delivery_version_id` BINARY(16) NOT NULL,
              `order_line_item_id` BINARY(16) NOT NULL,
              `order_line_item_version_id` BINARY(16) NOT NULL,
              `price` JSON NOT NULL,
              `total_price` INT(11) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
              `unit_price` INT(11) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.unitPrice"))) VIRTUAL,
              `quantity` INT(11) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.quantity"))) VIRTUAL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.order_delivery_position.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `json.order_delivery_position.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_delivery_position.order_delivery_id` FOREIGN KEY (`order_delivery_id`, `order_delivery_version_id`)
                REFERENCES `order_delivery` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_delivery_position.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
