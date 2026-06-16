<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1781614585RemoveMaintenanceIpWhitelistFromSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1781614585;
    }

    public function update(Connection $connection): void
    {
        $this->removeTrigger($connection, 'sales_channel_maintenance_ip_allowlist_insert');
        $this->removeTrigger($connection, 'sales_channel_maintenance_ip_allowlist_update');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'sales_channel', 'maintenance_ip_whitelist');
    }
}
