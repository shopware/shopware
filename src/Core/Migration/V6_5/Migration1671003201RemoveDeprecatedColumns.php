<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('system-settings')]
class Migration1671003201RemoveDeprecatedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1671003201;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'user_access_key', 'write_access')) {
            return;
        }

        // Add default value, so you don't need to provide the deprecated value, even if the destructive migrations are not executed immediately
        $connection->executeStatement('
            ALTER TABLE `user_access_key` CHANGE `write_access` `write_access` TINYINT(1) DEFAULT 0
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropWriteAccess($connection);
        $this->dropColumnsInCountryTable($connection);
        $this->dropOpenNewTabColumn($connection);
    }

    private function dropWriteAccess(Connection $connection): void
    {
        try {
            $connection->executeStatement('ALTER TABLE `user_access_key` DROP COLUMN `write_access`');
        } catch (\Throwable) {
        }
    }

    private function dropColumnsInCountryTable(Connection $connection): void
    {
        try {
            $connection->executeStatement(
                'DROP TRIGGER IF EXISTS country_tax_free_insert;'
            );
        } catch (\Throwable) {
        }

        try {
            $connection->executeStatement(
                'DROP TRIGGER IF EXISTS country_tax_free_update;'
            );
        } catch (\Throwable) {
        }

        try {
            $connection->executeStatement('
            ALTER TABLE `country`
            DROP COLUMN `tax_free`,
            DROP COLUMN `company_tax_free`;
        ');
        } catch (\Throwable) {
        }
    }

    private function dropOpenNewTabColumn(Connection $connection): void
    {
        try {
            $connection->executeStatement('ALTER TABLE `app_action_button` DROP COLUMN `open_new_tab`');
        } catch (\Throwable) {
        }
    }
}
