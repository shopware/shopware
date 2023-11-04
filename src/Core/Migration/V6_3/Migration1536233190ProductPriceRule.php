<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233190ProductPriceRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233190;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `product_price` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `rule_id` BINARY(16) NOT NULL,
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `currency_id` BINARY(16) NOT NULL,
              `price` JSON NOT NULL,
              `quantity_start` INT(11) NOT NULL,
              `quantity_end` INT(11) NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.product_price.price` CHECK (JSON_VALID(`price`)),
              CONSTRAINT `json.product_price.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.product_price.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_price.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_price.rule_id` FOREIGN KEY (`rule_id`)
                REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
