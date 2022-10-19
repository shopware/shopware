<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1574672450RemoteAddressIntoCustomerAndOrderCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574672450;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer`
            ADD `remote_address` VARCHAR(255) NULL;
        ');

        $connection->executeStatement('
            ALTER TABLE `order_customer`
            ADD `remote_address` VARCHAR(255) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
