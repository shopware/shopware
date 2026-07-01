<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782906166RepairUserActiveDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782906166;
    }

    public function update(Connection $connection): void
    {
        $column = TableHelper::getColumnOfTable($connection, 'user', 'active');

        // Once the default is `1`, the schema has already been repaired and existing inactive users
        // were migrated in the same run, so we can skip any follow-up updates.
        if ((string) $column->defaultValue !== '0') {
            return;
        }

        $connection->executeStatement('UPDATE `user` SET `active` = 1 WHERE `active` = 0');
        $connection->executeStatement('ALTER TABLE `user` MODIFY COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1');
    }
}
