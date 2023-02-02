<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1614765785ChangeDefaultValueOfAndLogicForSearchRankingConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614765785;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('UPDATE product_search_config SET and_logic = 0');
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
