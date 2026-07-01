<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

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
        $column = $connection->fetchAssociative(
            'SHOW COLUMNS FROM `user` LIKE :column',
            ['column' => 'active']
        );

        // Once the default is `1`, the schema has already been repaired and existing inactive users
        // were migrated in the same run, so we can skip any follow-up updates.
        if ($column === false || (string) ($column['Default'] ?? '') !== '0') {
            return;
        }

        $connection->executeStatement('UPDATE `user` SET `active` = 1 WHERE `active` = 0');
        $connection->executeStatement('ALTER TABLE `user` MODIFY COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
