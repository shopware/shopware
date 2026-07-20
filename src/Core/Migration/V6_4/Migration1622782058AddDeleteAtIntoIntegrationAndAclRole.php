<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1622782058AddDeleteAtIntoIntegrationAndAclRole extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622782058;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'integration', 'deleted_at')) {
            $connection->executeStatement('ALTER TABLE `integration` ADD COLUMN `deleted_at` DATETIME(3) NULL');
        }

        if (!TableHelper::columnExists($connection, 'acl_role', 'deleted_at')) {
            $connection->executeStatement('ALTER TABLE `acl_role` ADD COLUMN `deleted_at` DATETIME(3) NULL');
        }
    }
}
