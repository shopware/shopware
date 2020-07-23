<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1595499689RemoveGoogleShoppingRelatedTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595499689;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            DROP TABLE IF EXISTS google_shopping_ads_account;
            DROP TABLE IF EXISTS google_shopping_merchant_account;
            DROP TABLE IF EXISTS google_shopping_account;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
