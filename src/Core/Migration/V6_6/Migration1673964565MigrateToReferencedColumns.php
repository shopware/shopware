<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673964565MigrateToReferencedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673964565;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->executeQuery('
            SELECT COLUMN_NAME,EXTRA FROM information_schema.columns
                WHERE table_schema = :database
                  AND table_name = \'state_machine_history\'
                  AND (COLUMN_NAME = \'referenced_id\'
                    OR COLUMN_NAME = \'referenced_version_id\'
                    OR COLUMN_NAME = \'entity_id\');
        ', ['database' => $connection->getDatabase()])->fetchAllAssociativeIndexed();

        if ($columns['referenced_id']['EXTRA'] === 'STORED GENERATED') {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history`
                MODIFY COLUMN `referenced_id` BINARY(16) NOT NULL;
            ');
        }

        if ($columns['referenced_version_id']['EXTRA'] === 'STORED GENERATED') {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history`
                MODIFY COLUMN `referenced_version_id` BINARY(16) NOT NULL;
            ');
        }

        if (\array_key_exists('entity_id', $columns)) {
            $connection->executeStatement('
                ALTER TABLE `state_machine_history` DROP `entity_id`;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
