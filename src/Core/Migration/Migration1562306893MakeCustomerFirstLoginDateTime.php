<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1562306893MakeCustomerFirstLoginDateTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562306893;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `customer`
            MODIFY COLUMN `first_login` DATETIME(3) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
