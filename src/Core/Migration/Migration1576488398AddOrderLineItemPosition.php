<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1576488398AddOrderLineItemPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1576488398;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `order_line_item`
            ADD COLUMN `position` INT(11) NOT NULL DEFAULT 1 AFTER `good`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
