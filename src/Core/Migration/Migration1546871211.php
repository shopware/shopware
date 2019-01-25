<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546871211 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546871211;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate("
            ALTER TABLE `category` ADD `display_nested_products` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `child_count`;        
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
