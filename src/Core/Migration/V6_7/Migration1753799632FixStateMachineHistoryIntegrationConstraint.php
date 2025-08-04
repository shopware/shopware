<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class Migration1753799632FixStateMachineHistoryIntegrationConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753799632;
    }

    public function update(Connection $connection): void
    {
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists($connection, 'state_machine_history', 'fk.state_machine_history.integration_id');

        $connection->executeStatement('
            ALTER TABLE `state_machine_history`
            ADD CONSTRAINT `fk.state_machine_history.integration_id` FOREIGN KEY (`integration_id`)
                REFERENCES `integration` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }
}
