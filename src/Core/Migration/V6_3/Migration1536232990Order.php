<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232990Order extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232990;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `order` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `state_id` BINARY(16) NOT NULL,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `order_number` VARCHAR(64),
              `currency_id` BINARY(16) NOT NULL,
              `currency_factor` DOUBLE NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `billing_address_id` BINARY(16) NOT NULL,
              `billing_address_version_id` BINARY(16) NOT NULL,
              `price` JSON NOT NULL,
              `order_date` DATETIME(3) NOT NULL,
              `amount_total` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
              `amount_net` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.netPrice"))) VIRTUAL,
              `position_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.positionPrice"))) VIRTUAL,
              `tax_status` VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.taxStatus"))) VIRTUAL,
              `shipping_costs` JSON NOT NULL,
              `shipping_total` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`shipping_costs`, "$.totalPrice"))) VIRTUAL,
              `deep_link_code` VARCHAR(32) NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`id`, `version_id`),
               INDEX `idx.state_index` (`state_id`),
               UNIQUE `uniq.auto_increment` (`auto_increment`),
               UNIQUE `uniq.deep_link_code` (`deep_link_code`, `version_id`),
               CONSTRAINT `char_length.order.deep_link_code` CHECK (CHAR_LENGTH(`deep_link_code`) = 32),
               CONSTRAINT `json.order.price` CHECK  (JSON_VALID(`price`)),
               CONSTRAINT `json.order.shipping_costs` CHECK (JSON_VALID(`shipping_costs`)),
               CONSTRAINT `json.order.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
               CONSTRAINT `fk.order.currency_id` FOREIGN KEY (`currency_id`)
                 REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.order.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                 REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
