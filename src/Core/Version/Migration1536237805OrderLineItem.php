<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237805OrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237805;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_line_item` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `order_id` binary(16) NOT NULL,
              `order_tenant_id` binary(16) NOT NULL,
              `order_version_id` binary(16) NOT NULL,
              `parent_id` binary(16) DEFAULT NULL,
              `parent_tenant_id` binary(16) DEFAULT NULL,
              `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` mediumtext COLLATE utf8mb4_unicode_ci,
              `quantity` int(11) NOT NULL,
              `unit_price` double NOT NULL,
              `total_price` double NOT NULL,
              `type` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_order_line_item.order_id` FOREIGN KEY (`order_id`, `order_version_id`, `order_tenant_id`) REFERENCES `order` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
