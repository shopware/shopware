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
class Migration1673426317ImproveStateMachineHistoryQueryPerformance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673426317;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'state_machine_history', 'referenced_id')) {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history`
                ADD COLUMN `referenced_id` BINARY(16)
                GENERATED ALWAYS AS (
                    COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.id\'))), 0x0)
                ) STORED;
            ');
        }

        if (!TableHelper::columnExists($connection, 'state_machine_history', 'referenced_version_id')) {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history`
                ADD COLUMN `referenced_version_id` BINARY(16)
                GENERATED ALWAYS AS (
                    COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.version_id\'))), 0x0)
                ) STORED;
            ');
        }
    }
}
