<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234605SalesChannelShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234605;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `sales_channel_shipping_method` (
              `sales_channel_id` binary(16) NOT NULL,
              `sales_channel_tenant_id` binary(16) NOT NULL,
              `shipping_method_id` binary(16) NOT NULL,
              `shipping_method_version_id` binary(16) NOT NULL,
              `shipping_method_tenant_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`sales_channel_id`, `sales_channel_tenant_id`, `shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`),
              CONSTRAINT `fk_sales_channel_shipping_method.sales_channel_id` FOREIGN KEY (`sales_channel_id`, `sales_channel_tenant_id`) REFERENCES `sales_channel` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_sales_channel_shipping_method.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
