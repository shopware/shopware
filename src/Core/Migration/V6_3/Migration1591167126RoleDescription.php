<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1591167126RoleDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591167126;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `acl_role` ADD `description` longtext COLLATE \'utf8mb4_unicode_ci\' NULL AFTER `name`;');
        $connection->executeUpdate('ALTER TABLE `user` ADD `title` varchar(255) COLLATE \'utf8mb4_unicode_ci\' NULL AFTER `last_name`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
