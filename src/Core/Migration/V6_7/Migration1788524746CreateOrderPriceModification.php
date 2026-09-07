<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1788524746CreateOrderPriceModification extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788524746;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `order_price_modification` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
              `price` DOUBLE NOT NULL,
              `price_definition` JSON NULL,
              `tax_rules` JSON NULL,
              `position` INT(11) NOT NULL DEFAULT 0,
              `type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `referenced_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `payload` JSON NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.order_price_modification.price_definition` CHECK (JSON_VALID(`price_definition`)),
              CONSTRAINT `json.order_price_modification.tax_rules` CHECK (JSON_VALID(`tax_rules`)),
              CONSTRAINT `json.order_price_modification.payload` CHECK (JSON_VALID(`payload`)),
              CONSTRAINT `json.order_price_modification.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_price_modification.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
