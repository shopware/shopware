<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1562324772AddOrderDateToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562324772;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `order`
            CHANGE `order_date` `order_date_time` DATETIME(3) NOT NULL;
        ');

        $connection->executeUpdate('
            ALTER TABLE `order`
            ADD COLUMN `order_date` DATE GENERATED ALWAYS AS (CONVERT(`order_date_time`, DATE)) STORED AFTER `order_date_time`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
