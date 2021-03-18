<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1575883959ResetListingPrices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575883959;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('UPDATE product SET listing_prices = NULL');
        $this->registerIndexer($connection, 'Swag.ProductListingPriceIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
