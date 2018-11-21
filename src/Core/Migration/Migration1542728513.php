<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542728513 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542728513;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery("
ALTER TABLE `product`
CHANGE `supplier_number` `manufacturer_number` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `listing_prices`,
DROP `pseudo_sales`,
DROP `template`;        
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
