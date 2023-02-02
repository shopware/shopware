<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1622010069AddCartRules extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622010069;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cart` ADD `rule_ids` json NOT NULL AFTER `sales_channel_id`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
