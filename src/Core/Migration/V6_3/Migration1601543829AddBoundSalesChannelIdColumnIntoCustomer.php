<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1601543829AddBoundSalesChannelIdColumnIntoCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601543829;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer`
            ADD COLUMN `bound_sales_channel_id` BINARY(16) NULL DEFAULT NULL,
            ADD CONSTRAINT `fk.customer.bound_sales_channel_id` FOREIGN KEY (`bound_sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
