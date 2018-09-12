<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237803Order extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237803;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
              `order_customer_id` binary(16) NOT NULL,
              `order_customer_tenant_id` binary(16) NOT NULL,
              `order_customer_version_id` binary(16) NOT NULL,
              `order_state_id` binary(16) NOT NULL,
              `order_state_tenant_id` binary(16) NOT NULL,
              `order_state_version_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `payment_method_tenant_id` binary(16) NOT NULL,
              `payment_method_version_id` binary(16) NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `currency_tenant_id` binary(16) NOT NULL,
              `currency_version_id` binary(16) NOT NULL,
              `sales_channel_id` binary(16) NOT NULL,
              `sales_channel_tenant_id` binary(16) NOT NULL,
              `billing_address_id` binary(16) NOT NULL,
              `billing_address_tenant_id` binary(16) NOT NULL,
              `billing_address_version_id` binary(16) NOT NULL,
              `date` datetime(3) NOT NULL,
              `amount_total` double NOT NULL,
              `position_price` double NOT NULL,
              `shipping_total` double NOT NULL,
              `is_net` tinyint(1) NOT NULL,
              `is_tax_free` tinyint(1) NOT NULL,
              `deep_link_code` varchar(32) DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`, `version_id`, `tenant_id`),
               UNIQUE `auto_increment` (`auto_increment`),
               UNIQUE `deep_link_code` (`deep_link_code`, `version_id`, `tenant_id`),
               CONSTRAINT `fk_order.billing_address_id` FOREIGN KEY (`billing_address_id`, `billing_address_version_id`, `billing_address_tenant_id`) REFERENCES `order_address` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk_order.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk_order.order_customer_id` FOREIGN KEY (`order_customer_id`, `order_customer_version_id`, `order_customer_tenant_id`) REFERENCES `order_customer` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk_order.order_state_id` FOREIGN KEY (`order_state_id`, `order_state_version_id`, `order_state_tenant_id`) REFERENCES `order_state` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk_order.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk_order.sales_channel_id` FOREIGN KEY (`sales_channel_id`, `sales_channel_tenant_id`) REFERENCES `sales_channel` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CHECK (CHAR_LENGTH(`deep_link_code`) = 32)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
