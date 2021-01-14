<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1589447332AddFilterableToPropertyGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589447332;
    }

    /**
     * @throws DBALException
     */
    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `property_group`
            ADD COLUMN `filterable` TINYINT(1) NOT NULL DEFAULT 1
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
