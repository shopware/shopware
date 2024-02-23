<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1695778183UpdateStreetOfTableCustomerAddressToNotNull extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695778183;
    }

    public function update(Connection $connection): void
    {
        do {
            $affectedRows = $connection->executeStatement(
                'UPDATE `customer_address` set `street` = "" WHERE `street` IS NULL LIMIT 1000'
            );
        } while ($affectedRows > 0);

        $connection->executeStatement('ALTER TABLE `customer_address` MODIFY COLUMN `street` varchar(255) NOT NULL');
    }
}
