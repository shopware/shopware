<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610634383AddPositionToTaxEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610634383;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `tax` ADD COLUMN `position` INTEGER NOT NULL DEFAULT 0 AFTER `name`;
        ');

        // order taxes if default name was not changed
        $connection->executeUpdate('
            UPDATE `tax`
            SET `position` = 1
            WHERE `name` = "Standard rate"
        ');
        $connection->executeUpdate('
            UPDATE `tax`
            SET `position` = 2
            WHERE `name` = "Reduced rate"
        ');
        $connection->executeUpdate('
            UPDATE `tax`
            SET `position` = 3
            WHERE `name` = "Reduced rate 2"
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
