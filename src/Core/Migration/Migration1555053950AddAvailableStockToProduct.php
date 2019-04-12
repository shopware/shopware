<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1555053950AddAvailableStockToProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555053950;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `product`
            ADD COLUMN `available_stock` INT(11) NOT NULL DEFAULT 0 AFTER `stock`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
