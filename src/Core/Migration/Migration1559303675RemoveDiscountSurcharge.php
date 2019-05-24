<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1559303675RemoveDiscountSurcharge extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559303675;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('DROP TABLE discount_surcharge_translation');
        $connection->executeUpdate('DROP TABLE discount_surcharge');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
