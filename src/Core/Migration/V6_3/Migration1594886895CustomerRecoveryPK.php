<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1594886895CustomerRecoveryPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594886895;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeUpdate('
                ALTER TABLE `customer_recovery`
                ADD PRIMARY KEY (`id`);
            ');
        } catch (DBALException $e) {
            // PK already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}
