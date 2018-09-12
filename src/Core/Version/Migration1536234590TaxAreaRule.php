<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234590TaxAreaRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234590;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `tax_area_rule` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `tax_rate` decimal(10,2) NOT NULL,
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `country_area_id` binary(16) DEFAULT NULL,
              `country_area_tenant_id` binary(16) DEFAULT NULL,
              `country_area_version_id` binary(16) DEFAULT NULL,
              `country_id` binary(16) DEFAULT NULL,
              `country_tenant_id` binary(16) DEFAULT NULL,
              `country_version_id` binary(16) DEFAULT NULL,
              `country_state_id` binary(16) DEFAULT NULL,
              `country_state_tenant_id` binary(16) DEFAULT NULL,
              `country_state_version_id` binary(16) DEFAULT NULL,
              `tax_id` binary(16) NOT NULL,
              `tax_tenant_id` binary(16) NOT NULL,
              `tax_version_id` binary(16) NOT NULL,
              `customer_group_id` binary(16) NOT NULL,
              `customer_group_tenant_id` binary(16) NOT NULL,
              `customer_group_version_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_tax_area_rule.country_area_id` FOREIGN KEY (`country_area_id`, `country_area_version_id`, `country_area_tenant_id`) REFERENCES `country_area` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_tax_area_rule.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_tax_area_rule.country_state_id` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_tax_area_rule.customer_group_id` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_tax_area_rule.tax_id` FOREIGN KEY (`tax_id`, `tax_version_id`, `tax_tenant_id`) REFERENCES `tax` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
