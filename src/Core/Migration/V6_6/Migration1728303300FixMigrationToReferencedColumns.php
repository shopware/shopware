<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1728303300FixMigrationToReferencedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728303300;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'state_machine_history', 'entity_id')) {
            $this->reAddEntityIdColumn($connection);
        }

        if (!$this->referenceColumnHasGeneratedValue($connection, 'referenced_id')) {
            $this->reAddGeneratedValueForReferencedId($connection);
        }

        if (!$this->referenceColumnHasGeneratedValue($connection, 'referenced_version_id')) {
            $this->reAddGeneratedValueForReferencedVersionId($connection);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociativeIndexed(
            'SELECT COLUMN_NAME,EXTRA FROM information_schema.columns
                WHERE table_schema = :database
                  AND table_name = \'state_machine_history\'
                  AND (COLUMN_NAME = \'referenced_id\'
                    OR COLUMN_NAME = \'referenced_version_id\'
                    OR COLUMN_NAME = \'entity_id\');',
            ['database' => $connection->getDatabase()]
        );

        if ($columns['referenced_id']['EXTRA'] === 'STORED GENERATED') {
            $connection->executeStatement(
                'ALTER TABLE `state_machine_history`
                 MODIFY COLUMN `referenced_id` BINARY(16) NOT NULL;'
            );
        }

        if ($columns['referenced_version_id']['EXTRA'] === 'STORED GENERATED') {
            $connection->executeStatement(
                'ALTER TABLE `state_machine_history`
                 MODIFY COLUMN `referenced_version_id` BINARY(16) NOT NULL;'
            );
        }

        $this->dropColumnIfExists($connection, 'state_machine_history', 'entity_id');
    }

    private function reAddEntityIdColumn(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `state_machine_history`
             ADD COLUMN `entity_id` JSON NOT NULL;'
        );
    }

    private function referenceColumnHasGeneratedValue(Connection $connection, string $columnName): bool
    {
        return $connection->fetchOne(
            'SELECT EXTRA
             FROM information_schema.columns
             WHERE table_schema = :database
                AND table_name = \'state_machine_history\'
                AND COlUMN_NAME = :columnName',
            ['database' => $connection->getDatabase(), 'columnName' => $columnName]
        ) === 'STORED GENERATED';
    }

    private function reAddGeneratedValueForReferencedId(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `state_machine_history`
             MODIFY COLUMN `referenced_id` BINARY(16)
             GENERATED ALWAYS AS (
                COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.id\'))), 0x0)
             ) STORED;'
        );
    }

    private function reAddGeneratedValueForReferencedVersionId(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `state_machine_history`
             MODIFY COLUMN `referenced_version_id` BINARY(16)
             GENERATED ALWAYS AS (
                COALESCE(UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`entity_id`, \'$.version_id\'))), 0x0)
             ) STORED;'
        );
    }
}
