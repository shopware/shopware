<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553864174AddOrderDate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553864174;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `order`
              ADD COLUMN `order_date` DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) AFTER price;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `order`
            MODIFY COLUMN `order_date` DATETIME(3) NOT NULL
            ;'
        );
    }
}
