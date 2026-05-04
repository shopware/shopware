<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1642732351AddAppFlowActionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1642732351;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'flow_sequence', 'app_flow_action_id')) {
            $connection->executeStatement('ALTER TABLE `flow_sequence` ADD COLUMN `app_flow_action_id` BINARY(16) DEFAULT null AFTER `flow_id`');
            $connection->executeStatement(
                'ALTER TABLE `flow_sequence`
                ADD CONSTRAINT `fk.flow_sequence.app_flow_action_id` FOREIGN KEY (`app_flow_action_id`) REFERENCES `app_flow_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;'
            );
        }
    }
}
