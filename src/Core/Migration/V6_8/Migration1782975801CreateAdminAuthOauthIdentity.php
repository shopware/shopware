<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782975801CreateAdminAuthOauthIdentity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782975801;
    }

    public function update(Connection $connection): void
    {
        // No foreign key on `provider_id` on purpose: YAML-declared providers use
        // deterministic UUIDs without a corresponding `admin_auth_provider` row.
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_auth_oauth_identity` (
                `id`           BINARY(16)   NOT NULL,
                `provider_id`  BINARY(16)   NOT NULL,
                `user_id`      BINARY(16)   NOT NULL,
                `sub`          VARCHAR(255) NOT NULL,
                `email`        VARCHAR(255) NULL,
                `created_at`   DATETIME(3)  NOT NULL,
                `updated_at`   DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.admin_auth_oauth_identity.provider_sub` (`provider_id`, `sub`),
                KEY `idx.admin_auth_oauth_identity.user` (`user_id`),
                CONSTRAINT `fk.admin_auth_oauth_identity.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
