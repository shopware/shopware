<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1786447612FixSalesChannelAnalyticsConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786447612;
    }

    public function update(Connection $connection): void
    {
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists($connection, 'sales_channel', 'fk.sales_channel.analytics_id');

        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            ADD CONSTRAINT `fk.sales_channel.analytics_id`
            FOREIGN KEY (`analytics_id`)
            REFERENCES `sales_channel_analytics` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }
}
