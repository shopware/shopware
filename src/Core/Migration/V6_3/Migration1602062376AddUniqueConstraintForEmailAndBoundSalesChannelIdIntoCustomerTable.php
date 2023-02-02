<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1602062376AddUniqueConstraintForEmailAndBoundSalesChannelIdIntoCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1602062376;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `customer` ADD UNIQUE `uniq.customer.email_bound_sales_channel_id`(`email`, `bound_sales_channel_id`);');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
