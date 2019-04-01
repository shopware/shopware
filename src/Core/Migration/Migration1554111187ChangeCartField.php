<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554111187ChangeCartField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554111187;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `cart` DROP `cart`;');
        $connection->executeUpdate('ALTER TABLE `cart` ADD COLUMN `cart` LONGTEXT NOT NULL AFTER `name`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
