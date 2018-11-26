<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

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
              `version_id` binary(16) NOT NULL,
              `rule_id` binary(16) NOT NULL,
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `currency_id` binary(16) NOT NULL,
              `price` JSON NOT NULL,
              `quantity_start` INT(11) NOT NULL,
              `quantity_end` INT(11) NULL DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3) NULL DEFAULT NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `fk.product_price_rule.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_price_rule.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_price_rule.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
