<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1781614580AddMaintenanceIpAllowlistToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1781614580;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'sales_channel', 'maintenance_ip_allowlist')) {
            $connection->executeStatement('
                ALTER TABLE `sales_channel`
                ADD `maintenance_ip_allowlist` JSON NULL
            ');

            $connection->executeStatement('
                UPDATE `sales_channel`
                SET `maintenance_ip_allowlist` = `maintenance_ip_whitelist`
                WHERE `maintenance_ip_whitelist` IS NOT NULL
            ');
        }

        $this->addInsertTrigger($connection);
        $this->addUpdateTrigger($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, 'sales_channel_maintenance_ip_allowlist_insert');
        $this->removeTrigger($connection, 'sales_channel_maintenance_ip_allowlist_update');

        $this->dropColumnIfExists($connection, 'sales_channel', 'maintenance_ip_whitelist');
    }

    /**
     * Keeps `maintenance_ip_allowlist` and the deprecated `maintenance_ip_whitelist` column in sync on insert.
     * The new column wins if both are provided; otherwise the populated column is mirrored to the other.
     */
    private function addInsertTrigger(Connection $connection): void
    {
        $this->removeTrigger($connection, 'sales_channel_maintenance_ip_allowlist_insert');

        $query = 'CREATE TRIGGER sales_channel_maintenance_ip_allowlist_insert BEFORE INSERT ON sales_channel
            FOR EACH ROW BEGIN
                IF NEW.maintenance_ip_allowlist IS NULL AND NEW.maintenance_ip_whitelist IS NOT NULL THEN
                    SET NEW.maintenance_ip_allowlist = NEW.maintenance_ip_whitelist;
                END IF;
                SET NEW.maintenance_ip_whitelist = NEW.maintenance_ip_allowlist;
            END;';

        $this->createTrigger($connection, $query);
    }

    /**
     * Keeps `maintenance_ip_allowlist` and the deprecated `maintenance_ip_whitelist` column in sync on update.
     * Whichever column was changed is mirrored to the other; the new column wins if both were changed.
     */
    private function addUpdateTrigger(Connection $connection): void
    {
        $this->removeTrigger($connection, 'sales_channel_maintenance_ip_allowlist_update');

        $query = 'CREATE TRIGGER sales_channel_maintenance_ip_allowlist_update BEFORE UPDATE ON sales_channel
            FOR EACH ROW BEGIN
                IF NOT (NEW.maintenance_ip_allowlist <=> OLD.maintenance_ip_allowlist) THEN
                    SET NEW.maintenance_ip_whitelist = NEW.maintenance_ip_allowlist;
                ELSEIF NOT (NEW.maintenance_ip_whitelist <=> OLD.maintenance_ip_whitelist) THEN
                    SET NEW.maintenance_ip_allowlist = NEW.maintenance_ip_whitelist;
                END IF;
            END;';

        $this->createTrigger($connection, $query);
    }
}
