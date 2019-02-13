<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233040OrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233040;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_line_item` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `parent_id` BINARY(16) NULL,
              `parent_version_id` BINARY(16) NULL,
              `identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
              `quantity` INT(11) NOT NULL,
              `unit_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.unitPrice"))) VIRTUAL,
              `total_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
              `type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `payload` JSON NULL,
              `price_definition` JSON NULL,
              `price` JSON NOT NULL,
              `stackable`  TINYINT(1) NOT NULL DEFAULT 1,
              `removable`  TINYINT(1) NOT NULL DEFAULT 1,
              `priority` INT(11) NOT NULL DEFAULT 100,
              `good`  TINYINT(1) NOT NULL DEFAULT 1,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.payload` CHECK(JSON_VALID(`payload`)),
              CONSTRAINT `json.price` CHECK(JSON_VALID(`price`)),
              CONSTRAINT `json.price_definition` CHECK(JSON_VALID(`price_definition`)),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.order_line_item.order_id` FOREIGN KEY (`order_id`, `order_version_id`) 
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_line_item.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) 
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
