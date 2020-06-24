<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1592807051TriggerProductIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1592807051;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('UPDATE product SET listing_prices = NULL');

        $this->registerIndexer($connection, 'product.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
