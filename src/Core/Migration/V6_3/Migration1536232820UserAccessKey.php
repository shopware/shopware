<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232820UserAccessKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232820;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `user_access_key` (
              `id`                  BINARY(16)      NOT NULL,
              `user_id`             BINARY(16)      NOT NULL,
              `write_access`        TINYINT(1)      NOT NULL,
              `access_key`          VARCHAR(255)    NOT NULL,
              `secret_access_key`   VARCHAR(255)    NOT NULL,
              `last_usage_at`       DATETIME(3)     NULL,
              `custom_fields`       JSON            NULL,
              `created_at`          DATETIME(3)     NOT NULL,
              `updated_at`          DATETIME(3)     NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.user_access_key.user_id_` (`user_id`),
              INDEX `idx.user_access_key.access_key` (`access_key`),
              CONSTRAINT `json.user_access_key.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.user_access_key.user_id` FOREIGN KEY (`user_id`)
                REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
