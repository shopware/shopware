<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551794155AdjustUserRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551794155;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $query = <<<SQL
ALTER TABLE `user_recovery` ADD CONSTRAINT `fk.user_recovery.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT UNIQUE (`user_id`)
SQL;

        $connection->executeQuery($query);
    }
}
