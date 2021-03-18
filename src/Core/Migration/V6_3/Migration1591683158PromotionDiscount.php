<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1591683158PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591683158;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `promotion_discount` ADD `picker_key` VARCHAR(255) DEFAULT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
