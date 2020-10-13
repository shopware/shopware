<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1600330846ChangeActiveColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600330846;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` CHANGE `active` `active` tinyint unsigned NULL AFTER `product_number`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
