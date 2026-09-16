<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @no-indexer-required: 'sales_channel.indexer' only re-indexes many-to-many associations; the maintenance IP allowlist column is not part of its output.
 */
#[Package('discovery')]
class Migration1781614580AddMaintenanceIpAllowlistToSalesChannel extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1781614580;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel', 'maintenance_ip_allowlist', 'JSON');

        // backfill the new column from the deprecated one for existing sales channels;
        // the `IS NULL` guard keeps this a one-time, retry-safe copy that never clobbers
        // values written after creation. Ongoing writes are kept in sync by
        // SalesChannelMaintenanceIpAllowlistSyncSubscriber.
        $connection->executeStatement(<<<'SQL'
            UPDATE `sales_channel`
            SET `maintenance_ip_allowlist` = `maintenance_ip_whitelist`
            WHERE `maintenance_ip_whitelist` IS NOT NULL
              AND `maintenance_ip_allowlist` IS NULL
        SQL);
    }
}
