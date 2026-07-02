<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782975800CreateAdminAuthProvider extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782975800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_auth_provider` (
                `id`                BINARY(16)   NOT NULL,
                `name`              VARCHAR(255) NOT NULL,
                `type`              VARCHAR(50)  NOT NULL,
                `active`            TINYINT(1)   NOT NULL DEFAULT 0,
                `is_primary`        TINYINT(1)   NOT NULL DEFAULT 0,
                `is_second_factor`  TINYINT(1)   NOT NULL DEFAULT 0,
                `priority`          INT          NOT NULL DEFAULT 0,
                `config`            JSON         NULL,
                `created_at`        DATETIME(3)  NOT NULL,
                `updated_at`        DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                KEY `idx.admin_auth_provider.lookup` (`active`, `is_primary`, `type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
