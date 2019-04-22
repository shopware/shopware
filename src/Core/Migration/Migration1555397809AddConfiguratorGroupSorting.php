<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1555397809AddConfiguratorGroupSorting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555397809;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` ADD `configurator_group_sorting` json NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
