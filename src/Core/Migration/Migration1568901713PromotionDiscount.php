<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1568901713PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568901713;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `promotion_discount` ADD `sorter_key` VARCHAR(255) DEFAULT NULL;');
        $connection->executeUpdate('ALTER TABLE `promotion_discount` ADD `applier_key` VARCHAR(255) DEFAULT NULL;');
        $connection->executeUpdate('ALTER TABLE `promotion_discount` ADD `usage_key` VARCHAR(255) DEFAULT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
