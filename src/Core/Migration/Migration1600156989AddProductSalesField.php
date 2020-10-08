<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1600156989AddProductSalesField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600156989;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `product`
            ADD COLUMN `sales` INT(11) NOT NULL DEFAULT 0 AFTER `ean`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
