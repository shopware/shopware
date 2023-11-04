<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232800DeliveryTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE delivery_time (
              `id`          BINARY(16)      NOT NULL,
              `min`         INT(3)          NOT NULL,
              `max`         INT(3)          NOT NULL,
              `unit`        VARCHAR(255)    NOT NULL,
              `created_at`  DATETIME(3)     NOT NULL,
              `updated_at`  DATETIME(3)     NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE delivery_time_translation (
              `delivery_time_id`    BINARY(16)                              NOT NULL,
              `language_id`         BINARY(16)                              NOT NULL,
              `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields`       JSON                                    NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`delivery_time_id`, `language_id`),
              CONSTRAINT `fk.delivery_time_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.delivery_time_translation.delivery_time_id` FOREIGN KEY (`delivery_time_id`)
                REFERENCES `delivery_time` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
