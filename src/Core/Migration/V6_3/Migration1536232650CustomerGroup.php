<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232650CustomerGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232650;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `customer_group` (
              `id`              BINARY(16)  NOT NULL,
              `display_gross`   TINYINT(1)  NOT NULL DEFAULT 1,
              `created_at`      DATETIME(3) NOT NULL,
              `updated_at`      DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `customer_group_translation` (
              `customer_group_id`   BINARY(16)                              NOT NULL,
              `language_id`         BINARY(16)                              NOT NULL,
              `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields`       JSON                                    NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`customer_group_id`, `language_id`),
              CONSTRAINT `json.customer_group_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.customer_group_translation.language_id`
                FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.customer_group_translation.customer_group_id`
                FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
