<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233030OrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233030;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `order_line_item` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `order_id` BINARY(16) NOT NULL,
              `order_version_id` BINARY(16) NOT NULL,
              `parent_id` BINARY(16) NULL,
              `parent_version_id` BINARY(16) NULL,
              `identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `referenced_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
              `cover_id` BINARY(16) NULL,
              `quantity` INT(11) NOT NULL,
              `unit_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.unitPrice"))) VIRTUAL,
              `total_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
              `type` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `payload` JSON NULL,
              `price_definition` JSON NULL,
              `price` JSON NOT NULL,
              `stackable`  TINYINT(1) NOT NULL DEFAULT 1,
              `removable`  TINYINT(1) NOT NULL DEFAULT 1,
              `good`  TINYINT(1) NOT NULL DEFAULT 1,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `json.order_line_item.payload` CHECK(JSON_VALID(`payload`)),
              CONSTRAINT `json.order_line_item.price` CHECK(JSON_VALID(`price`)),
              CONSTRAINT `json.order_line_item.price_definition` CHECK(JSON_VALID(`price_definition`)),
              CONSTRAINT `json.order_line_item.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.order_line_item.order_id` FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_line_item.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`)
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.order_line_item.cover_id` FOREIGN KEY (`cover_id`)
                REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
