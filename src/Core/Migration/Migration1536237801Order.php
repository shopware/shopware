<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237801Order extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237801;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
              `order_customer_id` binary(16) NOT NULL,
              `order_customer_version_id` binary(16) NOT NULL,
              `order_state_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `currency_factor` DOUBLE NULL,
              `sales_channel_id` binary(16) NOT NULL,
              `billing_address_id` binary(16) NOT NULL,
              `billing_address_version_id` binary(16) NOT NULL,
              `date` datetime(3) NOT NULL,
              `price` json NOT NULL,
              `amount_total` double GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
              `amount_net` double GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.netPrice"))) VIRTUAL,
              `position_price` double GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.positionPrice"))) VIRTUAL,
              `tax_status` varchar(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.taxStatus")))VIRTUAL,
              `shipping_costs` json NOT NULL,
              `shipping_total` double GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
              `deep_link_code` varchar(32) NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`, `version_id`),
               UNIQUE `uniq.auto_increment` (`auto_increment`),
               UNIQUE `uniq.deep_link_code` (`deep_link_code`, `version_id`),
               CONSTRAINT `fk.order.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.order.order_customer_id` FOREIGN KEY (`order_customer_id`, `order_customer_version_id`) REFERENCES `order_customer` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.order.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.order.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.order.order_state_id` FOREIGN KEY (`order_state_id`) REFERENCES `order_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CHECK (JSON_VALID(`price`)),
               CHECK (JSON_VALID(`shipping_costs`)),
               CHECK (CHAR_LENGTH(`deep_link_code`) = 32)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
