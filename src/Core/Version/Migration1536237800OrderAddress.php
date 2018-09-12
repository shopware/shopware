<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237800OrderAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_address` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `country_id` binary(16) NOT NULL,
              `country_tenant_id` binary(16) NOT NULL,
              `country_version_id` binary(16) NOT NULL,
              `country_state_id` binary(16) DEFAULT NULL,
              `country_state_tenant_id` binary(16) DEFAULT NULL,
              `country_state_version_id` binary(16) DEFAULT NULL,
              `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `department` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NULL,
              `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
              `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
              `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_order_address.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk_order_address.country_state_id` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
