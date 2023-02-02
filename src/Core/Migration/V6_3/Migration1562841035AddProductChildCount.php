<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1562841035AddProductChildCount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562841035;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` ADD `child_count` INT(11)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
