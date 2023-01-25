<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232710Integration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232710;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `integration` (
              `id`                  BINARY(16)      NOT NULL,
              `write_access`        TINYINT(1)      NOT NULL DEFAULT 0,
              `access_key`          VARCHAR(255)    NOT NULL,
              `secret_access_key`   VARCHAR(255)    NOT NULL,
              `label`               VARCHAR(255)    NOT NULL,
              `custom_fields`       JSON            NULL,
              `created_at`          DATETIME(3)     NOT NULL,
              `updated_at`          DATETIME(3)     NULL,
              `last_usage_at`       DATETIME(3)     NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.access_key` (`access_key`),
              CONSTRAINT `json.integration.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
