<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1541422170OrderAddAmountNet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1541422170;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `order`
            ADD COLUMN `amount_net` DOUBLE NULL AFTER `amount_total`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
