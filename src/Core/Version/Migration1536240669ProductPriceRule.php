<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240669ProductPriceRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240669;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_price_rule` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `rule_id` binary(16) NOT NULL,
              `rule_tenant_id` binary(16) NOT NULL,
              `product_id` binary(16) NOT NULL,
              `product_tenant_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `currency_tenant_id` binary(16) NOT NULL,
              `currency_version_id` binary(16) NOT NULL,
              `price` LONGTEXT NOT NULL,
              `quantity_start` INT(11) NOT NULL,
              `quantity_end` INT(11) NULL DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3) NULL DEFAULT NULL,
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_product_price_rule.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_product_price_rule.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_product_price_rule.rule_id` FOREIGN KEY (`rule_id`, `rule_tenant_id`) REFERENCES `rule` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
