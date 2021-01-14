<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1563518181PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563518181;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `promotion_discount` ADD `max_value` FLOAT DEFAULT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
