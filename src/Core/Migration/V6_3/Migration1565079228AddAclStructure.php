<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1565079228AddAclStructure extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565079228;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `acl_role` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $connection->executeStatement('
            CREATE TABLE `acl_resource` (
                `resource` VARCHAR(255) NOT NULL,
                `privilege` VARCHAR(255) NOT NULL,
                `acl_role_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`resource`, `privilege`, `acl_role_id`),
                CONSTRAINT `fk.acl_resource.acl_role_id` FOREIGN KEY (`acl_role_id`)
                    REFERENCES `acl_role` (`id`) on DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $connection->executeStatement('
            CREATE TABLE `acl_user_role` (
                `user_id` BINARY(16) NOT NULL,
                `acl_role_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`user_id`, `acl_role_id`),
                CONSTRAINT `fk.acl_user_role.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk.acl_user_role.acl_role_id` FOREIGN KEY (`acl_role_id`)
                    REFERENCES `acl_role` (`id`) on DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $connection->executeStatement('ALTER TABLE `user` ADD `admin` tinyint(1) NULL AFTER `active`');

        $connection->executeStatement('UPDATE `user` SET `admin` = 1');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
