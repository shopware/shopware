<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610616655AddVisibleOnDetailToPropertyGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610616655;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `property_group`
            ADD COLUMN `visible_on_product_detail_page` TINYINT(1) DEFAULT 1
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
