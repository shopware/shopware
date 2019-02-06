<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551168237RemoveUnusedDatabaseFieldsForShippingMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551168237;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE shipping_method
            DROP COLUMN `bind_laststock`,
            DROP COLUMN `type`,
            DROP COLUMN `position`,
            DROP COLUMN `surcharge_calculation`,
            DROP COLUMN `tax_calculation`,
            DROP COLUMN `bind_time_from`,
            DROP COLUMN `bind_time_to`,
            DROP COLUMN `bind_instock`,
            DROP COLUMN `bind_weekday_from`,
            DROP COLUMN `bind_weekday_to`,
            DROP COLUMN `bind_weight_from`,
            DROP COLUMN `bind_weight_to`,
            DROP COLUMN `bind_sql`,
            DROP COLUMN `bind_price_from`,
            DROP COLUMN `bind_price_to`,
            DROP COLUMN `status_link`,
            DROP COLUMN `calculation_sql`;
        ');
    }
}
