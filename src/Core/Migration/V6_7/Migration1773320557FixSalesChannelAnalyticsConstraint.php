<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1773320557FixSalesChannelAnalyticsConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773320557;
    }

    /**
     * `sales_channel` has a nullable reference to `sales_channel_analytics`.
     * When we delete the referenced `sales_channel_analytics` entry, we want the reference in `sales_channel` to be set to null and not to cascade the delete to the whole sales channel.
     */
    public function update(Connection $connection): void
    {
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists($connection, 'sales_channel', 'fk.sales_channel.analytics_id');

        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            ADD CONSTRAINT `fk.sales_channel.analytics_id` FOREIGN KEY (`analytics_id`) 
                REFERENCES `sales_channel_analytics` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }
}
