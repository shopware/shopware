<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556787141CustomerMetaFieldsForRules extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556787141;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `customer`
            ADD COLUMN `last_order_date` DATETIME(3),
            ADD COLUMN `order_count` INT(5) NOT NULL DEFAULT 0
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
