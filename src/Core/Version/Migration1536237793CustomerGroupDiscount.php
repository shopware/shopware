<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237793CustomerGroupDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237793;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `customer_group_discount` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `customer_group_id` binary(16) NOT NULL,
              `customer_group_tenant_id` binary(16) NOT NULL,
              `customer_group_version_id` binary(16) NOT NULL,
              `percentage_discount` double NOT NULL,
              `minimum_cart_amount` double NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_customer_group_discount.customer_group_id` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
