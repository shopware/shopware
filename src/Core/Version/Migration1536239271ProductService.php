<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239271ProductService extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239271;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_service` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `product_id` binary(16) NOT NULL,
              `product_tenant_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `configuration_group_option_id` binary(16) NOT NULL,
              `configuration_group_option_tenant_id` binary(16) NOT NULL,
              `configuration_group_option_version_id` binary(16) NOT NULL,
              `tax_id` binary(16) NOT NULL,
              `tax_tenant_id` binary(16) NOT NULL,
              `tax_version_id` binary(16) NOT NULL,
              `price` LONGTEXT NULL,
              `prices` LONGTEXT NULL DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_product_service.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_product_service.configuration_group_option_id` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_product_service.tax_id` FOREIGN KEY (`tax_id`, `tax_version_id`, `tax_tenant_id`) REFERENCES `tax` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
