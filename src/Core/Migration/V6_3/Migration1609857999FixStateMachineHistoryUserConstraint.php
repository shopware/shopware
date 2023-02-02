<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1609857999FixStateMachineHistoryUserConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1609857999;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `state_machine_history` DROP FOREIGN KEY `fk.state_machine_history.user_id`;
        ');

        $connection->executeStatement('
            ALTER TABLE `state_machine_history`
            ADD CONSTRAINT `fk.state_machine_history.user_id` FOREIGN KEY (`user_id`)
                REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
