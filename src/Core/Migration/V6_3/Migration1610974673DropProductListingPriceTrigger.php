<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610974673DropProductListingPriceTrigger extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610974673;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        try {
            $connection->executeUpdate(
                '
                DROP TRIGGER `product_listing_price_update`'
            );
        } catch (\Throwable $e) {
        }
    }
}
