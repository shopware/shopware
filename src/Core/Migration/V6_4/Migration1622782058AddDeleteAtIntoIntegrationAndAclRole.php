<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1622782058AddDeleteAtIntoIntegrationAndAclRole extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622782058;
    }

    public function update(Connection $connection): void
    {
        $deletedAtColumnIntegration = $connection->fetchOne(
            'SHOW COLUMNS FROM `integration` WHERE `Field` LIKE :column;',
            ['column' => 'deleted_at']
        );

        if ($deletedAtColumnIntegration === false) {
            $connection->executeStatement('ALTER TABLE `integration` ADD COLUMN `deleted_at` DATETIME(3) NULL');
        }

        $deletedAtColumnAclRole = $connection->fetchOne(
            'SHOW COLUMNS FROM `acl_role` WHERE `Field` LIKE :column;',
            ['column' => 'deleted_at']
        );

        if ($deletedAtColumnAclRole === false) {
            $connection->executeStatement('ALTER TABLE `acl_role` ADD COLUMN `deleted_at` DATETIME(3) NULL');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
