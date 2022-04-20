<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1650249241UpdateTypeOfDepartmentAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1650249241;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `customer_address`
                MODIFY COLUMN `department` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');

        $connection->executeUpdate('
            ALTER TABLE `order_address`
                MODIFY COLUMN `department` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
