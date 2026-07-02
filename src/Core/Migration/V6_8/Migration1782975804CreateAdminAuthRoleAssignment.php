<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782975804CreateAdminAuthRoleAssignment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782975804;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_auth_role_assignment` (
                `id`             BINARY(16)   NOT NULL PRIMARY KEY,
                `user_id`        BINARY(16)   NOT NULL,
                `provider_key`   VARCHAR(255) NOT NULL,
                `acl_role_id`    BINARY(16)   NULL,
                `is_admin_grant` TINYINT(1)   NOT NULL DEFAULT 0,
                `created_at`     DATETIME(3)  NOT NULL,
                UNIQUE KEY `uniq.admin_auth_role_assignment.user_provider_role` (`user_id`, `provider_key`, `acl_role_id`),
                KEY `idx.admin_auth_role_assignment.user_id` (`user_id`),
                CONSTRAINT `fk.admin_auth_role_assignment.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.admin_auth_role_assignment.acl_role_id` FOREIGN KEY (`acl_role_id`)
                    REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
