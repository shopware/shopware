<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550146632DropSalesChannelCategory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550146632;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('DROP TABLE IF EXISTS `sales_channel_category`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
