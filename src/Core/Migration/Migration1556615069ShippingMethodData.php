<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556615069ShippingMethodData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556615069;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `shipping_method` 
                           MODIFY COLUMN `bind_shippingfree` TINYINT(1) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `shipping_method` 
                           DROP COLUMN `shipping_free`, 
                           DROP COLUMN `bind_shippingfree`;');
    }
}
