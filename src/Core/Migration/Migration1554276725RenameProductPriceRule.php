<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554276725RenameProductPriceRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554276725;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product_price_rule` RENAME TO `product_price`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
