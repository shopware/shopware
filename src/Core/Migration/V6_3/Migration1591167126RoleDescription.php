<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1591167126RoleDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591167126;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `acl_role` ADD `description` longtext COLLATE \'utf8mb4_unicode_ci\' NULL AFTER `name`;');
        $connection->executeStatement('ALTER TABLE `user` ADD `title` varchar(255) COLLATE \'utf8mb4_unicode_ci\' NULL AFTER `last_name`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
