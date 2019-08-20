<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1566293076AddAutoIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1566293076;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` ADD `auto_increment` int(11) NOT NULL AUTO_INCREMENT UNIQUE AFTER `version_id`;');
        $connection->executeUpdate('ALTER TABLE `category` ADD `auto_increment` int(11) NOT NULL AUTO_INCREMENT UNIQUE AFTER `version_id`;');
    }
}
