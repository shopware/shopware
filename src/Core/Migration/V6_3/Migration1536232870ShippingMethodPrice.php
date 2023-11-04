<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232870ShippingMethodPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232870;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `shipping_method_price` (
              `id`                  BINARY(16)      NOT NULL,
              `shipping_method_id`  BINARY(16)      NOT NULL,
              `calculation`         INT(1) unsigned NULL,
              `rule_id`             BINARY(16)      NULL,
              `currency_id`         BINARY(16)      NOT NULL,
              `calculation_rule_id` BINARY(16) NULL,
              `price`               DOUBLE NOT NULL,
              `quantity_start`      DOUBLE NULL,
              `quantity_end`        DOUBLE NULL,
              `custom_fields`       JSON NULL,
              `created_at`          DATETIME(3) NOT NULL,
              `updated_at`          DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `uniq.shipping_method_quantity_start` UNIQUE KEY (`shipping_method_id`, `rule_id`, `currency_id`, `quantity_start`),
              CONSTRAINT `json.shipping_method_price.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.shipping_method_price.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_price.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_price.rule_id` FOREIGN KEY (`rule_id`)
                REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.shipping_method_price.calculation_rule_id` FOREIGN KEY (`calculation_rule_id`)
                REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
