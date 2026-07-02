<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782975802CreateAdminAuthUserMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782975802;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_auth_user_method` (
                `id`            BINARY(16)   NOT NULL,
                `user_id`       BINARY(16)   NOT NULL,
                `type`          VARCHAR(50)  NOT NULL,
                `active`        TINYINT(1)   NOT NULL DEFAULT 0,
                `label`         VARCHAR(255) NULL,
                `secret`        BLOB         NULL,
                `credential`    JSON         NULL,
                `last_used_at`  DATETIME(3)  NULL,
                `created_at`    DATETIME(3)  NOT NULL,
                `updated_at`    DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                KEY `idx.admin_auth_user_method.user` (`user_id`, `type`, `active`),
                CONSTRAINT `fk.admin_auth_user_method.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
