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
        $this->dropColumnIfExists($connection, 'user_access_key', 'write_access');
    }

    private function dropColumnsInCountryTable(Connection $connection): void
    {
        $this->removeTrigger($connection, 'country_tax_free_insert');
        $this->removeTrigger($connection, 'country_tax_free_update');

        $this->dropColumnIfExists($connection, 'country', 'tax_free');
        $this->dropColumnIfExists($connection, 'country', 'company_tax_free');
    }

    private function dropOpenNewTabColumn(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'app_action_button', 'open_new_tab');
    }
}
