<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1594886773LogEntryPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594886773;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeUpdate('
                ALTER TABLE `log_entry`
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
