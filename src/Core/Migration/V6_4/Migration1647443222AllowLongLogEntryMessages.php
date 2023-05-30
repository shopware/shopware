<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1647443222AllowLongLogEntryMessages extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1647443222;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `log_entry`
                MODIFY COLUMN `message` LONGTEXT NOT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
