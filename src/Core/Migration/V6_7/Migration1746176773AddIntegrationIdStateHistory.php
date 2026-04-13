<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1746176773AddIntegrationIdStateHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1746176773;
    }

    public function update(Connection $connection): void
    {
        $columnAdded = $this->addColumn($connection, 'state_machine_history', 'integration_id', 'BINARY(16)');
        if ($columnAdded) {
            $connection->executeStatement('ALTER TABLE `state_machine_history` ADD CONSTRAINT `fk.state_machine_history.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE');
        }
    }
}
