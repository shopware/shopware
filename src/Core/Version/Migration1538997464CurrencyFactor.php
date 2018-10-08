<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1538997464CurrencyFactor extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1538997464;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `order`
            ADD COLUMN `currency_factor` DOUBLE NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
