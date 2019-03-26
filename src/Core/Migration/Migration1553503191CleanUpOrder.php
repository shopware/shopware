<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553503191CleanUpOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553503191;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `order`
              MODIFY COLUMN `date` DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3);'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `order`
            DROP COLUMN `date`
            ;'
        );
    }
}
