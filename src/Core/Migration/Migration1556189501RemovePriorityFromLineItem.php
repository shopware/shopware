<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556189501RemovePriorityFromLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556189501;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `order_line_item` DROP COLUMN `priority`');
    }
}
