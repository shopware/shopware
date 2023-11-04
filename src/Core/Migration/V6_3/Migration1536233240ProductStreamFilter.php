<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233240ProductStreamFilter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233240;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `product_stream_filter` (
              `id` BINARY(16) NOT NULL,
              `product_stream_id` BINARY(16) NOT NULL,
              `parent_id` BINARY(16) NULL,
              `type` VARCHAR(255) NOT NULL,
              `field` VARCHAR(255) NULL,
              `operator` VARCHAR(255) NULL,
              `value` LONGTEXT NULL,
              `parameters` LONGTEXT NULL,
              `position` INT(11) DEFAULT 0 NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.product_stream_filter.parameters` CHECK (JSON_VALID(`parameters`)),
              CONSTRAINT `json.product_stream_filter.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.product_stream_filter.product_stream_id` FOREIGN KEY (`product_stream_id`)
                REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_stream_filter.parent_id` FOREIGN KEY (`parent_id`)
                REFERENCES product_stream_filter (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
