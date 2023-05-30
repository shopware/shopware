<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232700VersionCommit extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232700;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `version_commit` (
              `id`              BINARY(16) NOT NULL,
              `auto_increment`  BIGINT NOT NULL AUTO_INCREMENT UNIQUE,
              `is_merge`        TINYINT(1) NOT NULL DEFAULT 0,
              `message`         VARCHAR(5000) NULL,
              `user_id`         BINARY(16) NULL,
              `integration_id`  BINARY(16) NULL,
              `version_id`      BINARY(16) NOT NULL,
              `created_at`      DATETIME(3) NOT NULL,
              `updated_at`      DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
