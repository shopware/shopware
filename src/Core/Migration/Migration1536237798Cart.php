<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237798Cart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237798;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `cart` (
              `version_id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `token` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
              `cart` LONGTEXT NOT NULL,
              `price` float NOT NULL,
              `line_item_count` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `currency_tenant_id` binary(16) NOT NULL,
              `currency_version_id` binary(16) NOT NULL,
              `shipping_method_id` binary(16) NOT NULL,
              `shipping_method_tenant_id` binary(16) NOT NULL,
              `shipping_method_version_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `payment_method_tenant_id` binary(16) NOT NULL,
              `payment_method_version_id` binary(16) NOT NULL,
              `country_id` binary(16) NOT NULL,
              `country_tenant_id` binary(16) NOT NULL,
              `country_version_id` binary(16) NOT NULL,
              `customer_id` binary(16) DEFAULT NULL,
              `customer_tenant_id` binary(16) DEFAULT NULL,
              `customer_version_id` binary(16) DEFAULT NULL,
              `sales_channel_id` binary(16) NOT NULL,
              `sales_channel_tenant_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              CHECK (JSON_VALID(`cart`)),
              PRIMARY KEY `token` (`token`, `name`, `tenant_id`),
              CONSTRAINT `fk_cart.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_cart.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_cart.customer_id` FOREIGN KEY (`customer_id`, `customer_version_id`, `customer_tenant_id`) REFERENCES `customer` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_cart.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_cart.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_cart.sales_channel_id` FOREIGN KEY (`sales_channel_id`, `sales_channel_tenant_id`) REFERENCES `sales_channel` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
