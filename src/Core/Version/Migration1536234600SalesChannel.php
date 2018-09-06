<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234600SalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234600;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `sales_channel` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `type_id` binary(16) NOT NULL,
              `type_tenant_id` binary(16) NOT NULL,
              `configuration` LONGTEXT NULL DEFAULT NULL,
              `access_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `currency_tenant_id` binary(16) NOT NULL,
              `currency_version_id` binary(16) NOT NULL,
              `payment_method_id` binary(16) NOT NULL,
              `payment_method_tenant_id` binary(16) NOT NULL,
              `payment_method_version_id` binary(16) NOT NULL,
              `shipping_method_id` binary(16) NOT NULL,
              `shipping_method_tenant_id` binary(16) NOT NULL,
              `shipping_method_version_id` binary(16) NOT NULL,
              `country_id` binary(16) NOT NULL,
              `country_version_id` binary(16) NOT NULL,
              `country_tenant_id` binary(16) NOT NULL,
              `active` tinyint(1) NOT NULL DEFAULT \'1\',
              `tax_calculation_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'vertical\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `tenant_id`),
              UNIQUE (`access_key`, `tenant_id`),
              CONSTRAINT `fk_sales_channel.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_sales_channel.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_sales_channel.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_sales_channel.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_sales_channel.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_sales_channel.type_id` FOREIGN KEY (`type_id`, `type_tenant_id`) REFERENCES `sales_channel_type` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
