<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1694426018AddEntityIndexToStateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1694426018;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'state_machine_history', 'idx.state_machine_history.referenced_entity')) {
            return;
        }

        $connection->executeStatement('
            CREATE INDEX `idx.state_machine_history.referenced_entity`
                ON `state_machine_history` (`referenced_id`, `referenced_version_id`);
        ');
    }
}
