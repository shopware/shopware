<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673426317ImproveStateMachineHistoryQueryPerformance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673426317;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->executeQuery('
            SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.columns
                WHERE table_schema = :database
                  AND table_name = \'state_machine_history\'
                  AND (COLUMN_NAME = \'referenced_id\'
                    OR COLUMN_NAME = \'referenced_version_id\');
        ', ['database' => $connection->getDatabase()])->fetchAllAssociativeIndexed();

        if (!\array_key_exists('referenced_id', $columns)) {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history`
                ADD COLUMN `referenced_id` BINARY(16)
                GENERATED ALWAYS AS (
                    COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.id\'))), 0x0)
                ) STORED;
            ');
        }

        if (!\array_key_exists('referenced_version_id', $columns)) {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history`
                ADD COLUMN `referenced_version_id` BINARY(16)
                GENERATED ALWAYS AS (
                    COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.version_id\'))), 0x0)
                ) STORED;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
