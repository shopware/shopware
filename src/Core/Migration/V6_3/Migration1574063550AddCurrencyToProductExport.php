<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1574063550AddCurrencyToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574063550;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE product_export ADD COLUMN currency_id BINARY(16) NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
