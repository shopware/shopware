<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1555056365AddDefaultValueForStockColumnOfProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555056365;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `product` 
            MODIFY COLUMN `stock` INT(11) NOT NULL DEFAULT 0;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
