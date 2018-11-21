<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542122407 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542122407;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
          ALTER TABLE `product`
              ADD `whitelist_ids` longtext NULL,
              ADD `blacklist_ids` longtext NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
