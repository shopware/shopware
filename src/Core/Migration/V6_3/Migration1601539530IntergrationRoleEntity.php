<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1601539530IntergrationRoleEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601539530;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `integration_role` (
              `integration_id` BINARY(16) NOT NULL,
              `acl_role_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`integration_id`, `acl_role_id`),
              CONSTRAINT `fk.integration_acl_role.acl_role_id` FOREIGN KEY (`acl_role_id`)
                REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.integration_acl_role.integration_id` FOREIGN KEY (`integration_id`)
                REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('ALTER TABLE `integration` ADD `admin` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `label`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
