<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1562324772AddOrderDateToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562324772;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `order`
            CHANGE `order_date` `order_date_time` DATETIME(3) NOT NULL;
        ');

        $connection->executeStatement('
            ALTER TABLE `order`
            ADD COLUMN `order_date` DATE GENERATED ALWAYS AS (CONVERT(`order_date_time`, DATE)) STORED AFTER `order_date_time`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
