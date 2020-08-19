<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597929564AddOrderRefundPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597929564;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `order_refund_position` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_refund_id` BINARY(16) NOT NULL,
              `order_refund_version_id` BINARY(16) NOT NULL,
              `line_item_id` BINARY(16) NULL,
              `line_item_version_id` BINARY(16) NULL,
              `line_item_price` JSON NOT NULL,
              `line_item_total_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`line_item_price`, "$.totalPrice"))) VIRTUAL,
              `line_item_unit_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`line_item_price`, "$.unitPrice"))) VIRTUAL,
              `line_item_quantity` INT(11) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`line_item_price`, "$.quantity"))) VIRTUAL,
              `payload` JSON NOT NULL,
              `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `refund_price` JSON NOT NULL,
              `refund_total_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`refund_price`, "$.totalPrice"))) VIRTUAL,
              `refund_unit_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`refund_price`, "$.unitPrice"))) VIRTUAL,
              `refund_quantity` INT(11) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`refund_price`, "$.quantity"))) VIRTUAL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.order_refund_position.payload` CHECK (JSON_VALID(`payload`)),
              CONSTRAINT `json.order_refund_position.line_item_price` CHECK (JSON_VALID(`line_item_price`)),
              CONSTRAINT `json.order_refund_position.refund_price` CHECK (JSON_VALID(`refund_price`)),
              CONSTRAINT `json.order_refund_position.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_refund_position.order_refund_id` FOREIGN KEY (`order_refund_id`, `order_refund_version_id`)
                REFERENCES `order_refund` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_refund_position.line_item_id` FOREIGN KEY (`line_item_id`, `line_item_version_id`)
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
