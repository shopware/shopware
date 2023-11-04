<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1594886773LogEntryPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594886773;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement('
                ALTER TABLE `log_entry`
                ADD PRIMARY KEY (`id`);
            ');
        } catch (Exception) {
            // PK already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}
